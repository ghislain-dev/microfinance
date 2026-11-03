<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Policies;

use App\Domain\Organisation\Acces\Droit;
use App\Domain\Tontines\Models\Cycle;
use App\Models\User;

class CyclePolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->can(Droit::MembresVoir->value);
    }

    public function view(User $acteur, Cycle $modele): bool
    {
        return $modele->organisation_id === $acteur->organisation_id && $acteur->can(Droit::MembresVoir->value);
    }

    public function create(User $acteur): bool
    {
        return $acteur->can(Droit::TontinesGerer->value);
    }

    public function update(User $acteur, Cycle $modele): bool
    {
        return $modele->organisation_id === $acteur->organisation_id && $acteur->can(Droit::TontinesGerer->value);
    }

    /**
     * Cotisation collectée sur le terrain pour la prochaine séance (ADR 022).
     */
    public function collecter(User $acteur, Cycle $modele): bool
    {
        return $modele->organisation_id === $acteur->organisation_id && $acteur->can(Droit::CollecteSaisir->value);
    }
}
