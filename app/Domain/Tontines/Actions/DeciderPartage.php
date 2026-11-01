<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Actions;

use App\Domain\Epargne\Services\RegleEpargne;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Partage;
use App\Domain\Tontines\Services\EpargneDuMembre;
use App\Domain\Tontines\StatutCycle;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Validation du comité : compensation des dettes, versement des parts sur l'épargne, clôture du cycle.
 * Tout ou rien (une transaction).
 */
final class DeciderPartage
{
    public function __construct(
        private readonly MoteurFinancier $moteur,
        private readonly RegleEpargne $regles,
        private readonly EpargneDuMembre $epargne,
    ) {}

    public function valider(User $comite, Partage $partage, string $motif): Partage
    {
        $motif = $this->motif($motif);
        AutoriserDansOrganisation::autoriser($comite, 'decider', $partage);

        return DB::transaction(function () use ($comite, $partage, $motif) {
            $cycle = Cycle::query()->with(['compteFonds', 'compteCreances'])->whereKey($partage->cycle_id)->lockForUpdate()->firstOrFail();

            foreach ($partage->lignes()->orderBy('id')->get() as $ligne) {
                $membreId = (int) $ligne->getAttribute('membre_id');
                $compensation = $ligne->compensation;
                $verse = $ligne->verse;

                if ($compensation->estPositif()) {
                    $this->moteur->executer("partage:{$partage->id}:membre:{$membreId}:compensation", 'tontine_compensation',
                        [LigneEcriture::debit($cycle->compteFonds, $compensation), LigneEcriture::credit($cycle->compteCreances, $compensation)],
                        validateur: $comite);
                }
                if ($verse->estPositif()) {
                    $compte = $this->epargne->exiger($membreId, $cycle->devise, 'membre');
                    $operation = $this->moteur->executer("partage:{$partage->id}:membre:{$membreId}:versement", 'tontine_partage',
                        [LigneEcriture::debit($cycle->compteFonds, $verse), LigneEcriture::credit($compte->compte, $verse)],
                        validateur: $comite);
                    $this->regles->enregistrerMouvement($operation->id, $compte, 'partage_tontine', $verse);
                }
            }

            $partage->forceFill(['statut' => 'valide', 'decide_par_id' => $comite->id, 'motif_decision' => $motif, 'decide_le' => now()])->save();
            $cycle->forceFill(['statut' => StatutCycle::Cloture])->save();

            return $partage;
        });
    }

    public function rejeter(User $comite, Partage $partage, string $motif): Partage
    {
        $motif = $this->motif($motif);
        AutoriserDansOrganisation::autoriser($comite, 'decider', $partage);
        $partage->forceFill(['statut' => 'rejete', 'decide_par_id' => $comite->id, 'motif_decision' => $motif, 'decide_le' => now()])->save();

        return $partage;
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
