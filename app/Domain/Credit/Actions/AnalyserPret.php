<?php

declare(strict_types=1);

namespace App\Domain\Credit\Actions;

use App\Domain\Credit\CreditRefuse;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\StatutPret;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;

/**
 * L'agent de crédit (ou le gérant) consigne son analyse et soumet la demande au comité.
 */
final class AnalyserPret
{
    public function executer(User $agent, Pret $pret, string $analyse): Pret
    {
        AutoriserDansOrganisation::autoriser($agent, 'analyser', $pret);
        $analyse = trim($analyse);
        if ($analyse === '' || mb_strlen($analyse) > 5_000) {
            throw new CreditRefuse('Analyse obligatoire (5 000 caractères au plus).');
        }

        $pret->forceFill(['analyse' => $analyse, 'statut' => StatutPret::Soumis])->save();

        return $pret;
    }
}
