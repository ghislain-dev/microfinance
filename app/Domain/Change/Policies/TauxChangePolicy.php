<?php

declare(strict_types=1);

namespace App\Domain\Change\Policies;

use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

class TauxChangePolicy
{
    /**
     * Consulter les taux : ceux qui les saisissent et ceux qui convertissent.
     */
    public function viewAny(User $acteur): bool
    {
        return $acteur->canAny([Droit::ChangeSaisirTaux->value, Droit::EpargneVirer->value]);
    }

    public function create(User $acteur): bool
    {
        return $acteur->can(Droit::ChangeSaisirTaux->value);
    }
}
