<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Policies;

use App\Domain\Epargne\Models\TypeCompteEpargne;
use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

class TypeCompteEpargnePolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->can(Droit::EpargneParametrer->value);
    }

    public function create(User $acteur): bool
    {
        return $acteur->can(Droit::EpargneParametrer->value);
    }

    public function update(User $acteur, TypeCompteEpargne $type): bool
    {
        return $type->organisation_id === $acteur->organisation_id && $acteur->can(Droit::EpargneParametrer->value);
    }
}
