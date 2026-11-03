<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Policies;

use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

/**
 * Gestion de la collecte (téléphones, tournées, paramètres) : le gérant (ADR 022).
 */
class CollectePolicy
{
    public function gerer(User $acteur): bool
    {
        return $acteur->can(Droit::CollecteGerer->value);
    }
}
