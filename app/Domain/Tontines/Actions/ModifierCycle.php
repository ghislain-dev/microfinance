<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Actions;

use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\ParametresCycle;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\TontineRefusee;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;

final class ModifierCycle
{
    /**
     * @param  array<string, mixed>  $donnees  seules les clés fournies changent (la devise ne change pas)
     */
    public function executer(User $acteur, Cycle $cycle, array $donnees): Cycle
    {
        AutoriserDansOrganisation::autoriser($acteur, 'update', $cycle);
        if ($cycle->statut !== StatutCycle::EnPreparation) {
            throw new TontineRefusee('Les paramètres d’un cycle démarré sont figés.');
        }

        unset($donnees['devise']);
        ParametresCycle::appliquer($cycle, $cycle->groupe->type, $donnees);
        $cycle->save();

        return $cycle;
    }
}
