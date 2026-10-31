<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Policies;

use App\Domain\Organisation\Acces\Droit;
use App\Domain\Organisation\Models\Agence;
use App\Models\User;

class AgencePolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->can(Droit::AgencesVoir->value);
    }

    public function view(User $acteur, Agence $agence): bool
    {
        return $acteur->organisation_id === $agence->organisation_id
            && $acteur->can(Droit::AgencesVoir->value);
    }

    public function create(User $acteur): bool
    {
        return $acteur->can(Droit::AgencesGerer->value);
    }

    public function update(User $acteur, Agence $agence): bool
    {
        return $acteur->organisation_id === $agence->organisation_id
            && $acteur->can(Droit::AgencesGerer->value);
    }
}
