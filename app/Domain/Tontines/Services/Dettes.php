<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Services;

use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Models\Cotisation;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\DetteTontine;
use App\Domain\Tontines\Models\LignePartage;
use App\Support\Montant\Montant;

/**
 * Dette d'un membre envers un cycle : dettes constatées, moins règlements en séance et compensations
 * sur sa part d'un partage validé.
 */
final class Dettes
{
    public function du(Cycle $cycle, Membre $membre): Montant
    {
        $dettes = (int) DetteTontine::query()->where('cycle_id', $cycle->id)->where('membre_id', $membre->id)->sum('montant');
        $regle = (int) Cotisation::query()->where('membre_id', $membre->id)->where('type', 'reglement')
            ->whereIn('seance_id', fn ($q) => $q->select('id')->from('seances_tontine')->where('cycle_id', $cycle->id))
            ->sum('montant');

        $compense = (int) LignePartage::query()->where('membre_id', $membre->id)
            ->whereIn('partage_id', fn ($q) => $q->select('id')->from('partages_tontine')->where('cycle_id', $cycle->id)->where('statut', 'valide'))
            ->sum('compensation');

        return new Montant($dettes - $regle - $compense, $cycle->devise);
    }
}
