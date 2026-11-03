<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Policies;

use App\Domain\Collecte\Models\Appareil;
use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

class AppareilPolicy
{
    public function decider(User $acteur, Appareil $appareil): bool
    {
        return $appareil->organisation_id === $acteur->organisation_id && $acteur->can(Droit::CollecteGerer->value);
    }
}
