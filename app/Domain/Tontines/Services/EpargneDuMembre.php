<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Services;

use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Tontines\TontineRefusee;
use App\Support\Montant\Devise;

/**
 * Compte d'épargne qui reçoit les versements de tontine (tour, aide, partage) : le premier du membre
 * dans la devise du cycle.
 */
final class EpargneDuMembre
{
    public function exiger(int $membreId, Devise $devise, string $pour): CompteEpargne
    {
        return CompteEpargne::query()->with(['compte', 'membre'])->where('membre_id', $membreId)
            ->whereHas('type', fn ($q) => $q->where('devise', $devise->value))->orderBy('id')->first()
            ?? throw new TontineRefusee("Le {$pour} n’a pas de compte d’épargne en {$devise->value} : ouvrez-en un d’abord.");
    }
}
