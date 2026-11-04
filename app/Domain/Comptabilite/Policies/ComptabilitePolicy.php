<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Policies;

use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

/**
 * États comptables : comptable et gérant ; correspondance SYSCOHADA : comptable seul (ADR 023).
 */
class ComptabilitePolicy
{
    public function voir(User $acteur): bool
    {
        return $acteur->can(Droit::ComptaVoir->value);
    }

    public function parametrer(User $acteur): bool
    {
        return $acteur->can(Droit::ComptaParametrer->value);
    }
}
