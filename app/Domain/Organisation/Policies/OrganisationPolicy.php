<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Policies;

use App\Domain\Organisation\Acces\Droit;
use App\Domain\Organisation\Models\Organisation;
use App\Models\User;

class OrganisationPolicy
{
    public function view(User $acteur, Organisation $organisation): bool
    {
        return $acteur->organisation_id === $organisation->id;
    }

    public function update(User $acteur, Organisation $organisation): bool
    {
        return $acteur->organisation_id === $organisation->id
            && $acteur->can(Droit::OrganisationModifier->value);
    }
}
