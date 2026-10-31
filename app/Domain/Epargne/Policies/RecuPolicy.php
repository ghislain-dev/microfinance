<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Policies;

use App\Domain\Epargne\Models\Recu;
use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

class RecuPolicy
{
    public function view(User $acteur, Recu $recu): bool
    {
        return $recu->organisation_id === $acteur->organisation_id && $acteur->can(Droit::MembresVoir->value);
    }
}
