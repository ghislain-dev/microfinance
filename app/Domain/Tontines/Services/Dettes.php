<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Services;

use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Models\Cotisation;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\DetteTontine;
use App\Support\Montant\Montant;

/**
 * Dette d'un membre envers un cycle : dettes constatées moins règlements.
 */
final class Dettes
{
    public function du(Cycle $cycle, Membre $membre): Montant
    {
        $dettes = (int) DetteTontine::query()->where('cycle_id', $cycle->id)->where('membre_id', $membre->id)->sum('montant');
        $regle = (int) Cotisation::query()->where('membre_id', $membre->id)->where('type', 'reglement')
            ->whereIn('seance_id', fn ($q) => $q->select('id')->from('seances_tontine')->where('cycle_id', $cycle->id))
            ->sum('montant');

        return new Montant($dettes - $regle, $cycle->devise);
    }
}
