<?php

declare(strict_types=1);

namespace App\Domain\Credit\Actions;

use App\Domain\Credit\Models\ProduitCredit;
use App\Domain\Credit\Models\SeuilCredit;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;

/**
 * Seuil au-delà duquel le gérant approuve après le comité, par devise (paramétrage du crédit).
 */
final class FixerSeuilCredit
{
    public function executer(User $acteur, Devise $devise, Montant $montant): SeuilCredit
    {
        AutoriserDansOrganisation::autoriser($acteur, 'create', ProduitCredit::class);

        $seuil = SeuilCredit::query()->firstOrNew(['devise' => $devise]);
        $seuil->forceFill(['organisation_id' => $acteur->organisation_id, 'montant' => $montant])->save();

        return $seuil;
    }
}
