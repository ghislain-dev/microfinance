<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Actions;

use App\Domain\Epargne\Models\SeuilValidation;
use App\Domain\Epargne\Models\TypeCompteEpargne;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;

/**
 * Seuil de validation des gros décaissements, par devise (paramétrage de l'épargne : admin, gérant).
 */
final class FixerSeuil
{
    public function executer(User $acteur, Devise $devise, Montant $montant): SeuilValidation
    {
        AutoriserDansOrganisation::autoriser($acteur, 'create', TypeCompteEpargne::class);

        $seuil = SeuilValidation::query()->firstOrNew(['devise' => $devise]);
        $seuil->forceFill(['organisation_id' => $acteur->organisation_id, 'montant' => $montant])->save();

        return $seuil;
    }
}
