<?php

declare(strict_types=1);

namespace App\Domain\Change\Actions;

use App\Domain\Change\Models\TauxChange;
use App\Domain\Change\Taux;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Temps\Horloge;
use InvalidArgumentException;

/**
 * Taux du jour (gérant ou comptable). Une correction crée une nouvelle ligne : la plus récente s'applique.
 */
final class SaisirTaux
{
    public function executer(User $acteur, Taux $achat, Taux $vente): TauxChange
    {
        AutoriserDansOrganisation::autoriser($acteur, 'create', TauxChange::class);
        if ($achat->comparer($vente) > 0) {
            throw new InvalidArgumentException('Le taux d’achat ne peut pas dépasser le taux de vente.');
        }

        $taux = new TauxChange;
        $taux->forceFill([
            'organisation_id' => $acteur->organisation_id,
            'date_application' => Horloge::aujourdhui($acteur->organisation_id)->toDateString(),
            'achat' => $achat->millioniemes,
            'vente' => $vente->millioniemes,
            'saisi_par_id' => $acteur->id,
        ])->save();

        return $taux;
    }
}
