<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Actions;

use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Models\Aide;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\TontineRefusee;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Montant;

/**
 * Demande d'aide sur le fonds social (gérant) ; versée seulement après la validation du comité.
 */
final class DemanderAide
{
    public function executer(User $acteur, Cycle $cycle, Membre $membre, Montant $montant, string $motif): Aide
    {
        AutoriserDansOrganisation::autoriser($acteur, 'update', $cycle);
        $motif = trim($motif);
        if ($cycle->statut !== StatutCycle::EnCours) {
            throw new TontineRefusee('Les aides se demandent pendant un cycle en cours.');
        }
        if (! $cycle->participants()->whereKey($membre->id)->exists()) {
            throw new TontineRefusee("{$membre->numero} ne participe pas à ce cycle.");
        }
        if ($montant->devise !== $cycle->devise || ! $montant->estPositif() || $motif === '' || mb_strlen($motif) > 500) {
            throw new TontineRefusee("Montant positif en {$cycle->devise->value} et motif obligatoire.");
        }

        $aide = new Aide;
        $aide->forceFill([
            'organisation_id' => $cycle->organisation_id, 'cycle_id' => $cycle->id, 'membre_id' => $membre->id,
            'montant' => $montant->centimes, 'motif' => $motif, 'statut' => 'en_attente', 'demande_par_id' => $acteur->id,
        ])->save();

        return $aide;
    }
}
