<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Remuneration\Actions;

use App\Domain\Epargne\Remuneration\Models\Remuneration;
use App\Domain\Epargne\Remuneration\StatutRemuneration;
use App\Domain\Epargne\Services\RegleEpargne;
use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\MoteurFinancier;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Validation (versement) ou rejet d'un lot d'intérêts ou de ristourne. Tout le lot est versé ou rien :
 * intérêts → charge « intérêts sur épargne » ; ristourne → « excédents à répartir » (jamais négatif).
 */
final class DeciderRemuneration
{
    public function __construct(
        private readonly MoteurFinancier $moteur,
        private readonly OuvrirCompte $ouvrirCompte,
        private readonly RegleEpargne $regles,
    ) {}

    public function valider(User $validateur, Remuneration $lot, string $motif): Remuneration
    {
        $motif = $this->motif($motif);
        AutoriserDansOrganisation::autoriser($validateur, 'decider', $lot);

        return DB::transaction(function () use ($validateur, $lot, $motif) {
            $lot = Remuneration::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();
            $source = $lot->type === 'interets'
                ? $this->ouvrirCompte->obtenir("CHARGE-INTERETS-EPARGNE-{$lot->devise->value}", "Intérêts sur épargne ({$lot->devise->value})", NatureCompte::Charge, $lot->devise)
                : $this->ouvrirCompte->obtenir("EXCEDENTS-A-REPARTIR-{$lot->devise->value}", "Excédents à répartir ({$lot->devise->value})", NatureCompte::FondsPropres, $lot->devise);

            foreach ($lot->lignes()->with('compteEpargne.compte')->orderBy('id')->get() as $ligne) {
                $operation = $this->moteur->executer("remuneration:{$lot->id}:{$ligne->id}", $lot->type, [
                    LigneEcriture::debit($source, $ligne->montant),
                    LigneEcriture::credit($ligne->compteEpargne->compte, $ligne->montant),
                ], motif: "{$lot->libelleType()} {$lot->periode}", validateur: $validateur);
                $this->regles->enregistrerMouvement($operation->id, $ligne->compteEpargne, $lot->type, $ligne->montant);
            }

            return $this->decider($lot, $validateur, $motif, StatutRemuneration::Validee);
        });
    }

    public function rejeter(User $validateur, Remuneration $lot, string $motif): Remuneration
    {
        $motif = $this->motif($motif);
        AutoriserDansOrganisation::autoriser($validateur, 'decider', $lot);

        return $this->decider($lot, $validateur, $motif, StatutRemuneration::Rejetee);
    }

    private function decider(Remuneration $lot, User $validateur, string $motif, StatutRemuneration $statut): Remuneration
    {
        $lot->forceFill(['statut' => $statut, 'decide_par_id' => $validateur->id, 'decide_le' => now(), 'motif_decision' => $motif])->save();

        return $lot;
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
