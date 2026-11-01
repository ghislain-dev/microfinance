<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Actions;

use App\Domain\Epargne\Services\RegleEpargne;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Tontines\Models\Aide;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Services\EpargneDuMembre;
use App\Domain\Tontines\TontineRefusee;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Montant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Le comité valide (versement sur l'épargne du membre, si le fonds social suffit) ou rejette une aide.
 */
final class DeciderAide
{
    public function __construct(
        private readonly MoteurFinancier $moteur,
        private readonly RegleEpargne $regles,
        private readonly EpargneDuMembre $epargne,
    ) {}

    public function valider(User $comite, Aide $aide, string $motif): Aide
    {
        $motif = $this->motif($motif);
        AutoriserDansOrganisation::autoriser($comite, 'decider', $aide);

        return DB::transaction(function () use ($comite, $aide, $motif) {
            $cycle = Cycle::query()->with('compteFondsSocial')->findOrFail($aide->cycle_id);
            $montant = new Montant((int) $aide->getAttribute('montant'), $cycle->devise);
            $disponible = $this->moteur->soldeReel($cycle->compteFondsSocial);
            if ($montant->comparer($disponible) > 0) {
                throw new TontineRefusee("Le fonds social ne contient que {$disponible->formate()}.");
            }
            $compte = $this->epargne->exiger((int) $aide->getAttribute('membre_id'), $cycle->devise, 'membre aidé');

            $operation = $this->moteur->executer("aide-tontine:{$aide->id}", 'tontine_aide',
                [LigneEcriture::debit($cycle->compteFondsSocial, $montant), LigneEcriture::credit($compte->compte, $montant)],
                motif: (string) $aide->getAttribute('motif'), validateur: $comite);
            $this->regles->enregistrerMouvement($operation->id, $compte, 'aide_fonds_social', $montant);

            $aide->forceFill(['statut' => 'validee', 'decide_par_id' => $comite->id, 'motif_decision' => $motif, 'operation_id' => $operation->id])->save();

            return $aide;
        });
    }

    public function rejeter(User $comite, Aide $aide, string $motif): Aide
    {
        $motif = $this->motif($motif);
        AutoriserDansOrganisation::autoriser($comite, 'decider', $aide);
        $aide->forceFill(['statut' => 'rejetee', 'decide_par_id' => $comite->id, 'motif_decision' => $motif])->save();

        return $aide;
    }

    private function motif(string $motif): string
    {
        $motif = trim($motif);
        if ($motif === '' || mb_strlen($motif) > 500) {
            throw new InvalidArgumentException('Motif obligatoire (500 caractères au plus).');
        }

        return $motif;
    }
}
