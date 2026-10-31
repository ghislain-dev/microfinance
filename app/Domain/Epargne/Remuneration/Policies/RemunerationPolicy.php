<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Remuneration\Policies;

use App\Domain\Epargne\Remuneration\Models\Remuneration;
use App\Domain\Epargne\Remuneration\StatutRemuneration;
use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

class RemunerationPolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->can(Droit::EpargneRemuneration->value);
    }

    public function create(User $acteur): bool
    {
        return $acteur->can(Droit::EpargneRemuneration->value);
    }

    /**
     * Gérant ou comptable ; jamais la personne qui a saisi le lot (4 yeux).
     */
    public function decider(User $acteur, Remuneration $lot): bool
    {
        return $lot->organisation_id === $acteur->organisation_id
            && $lot->statut === StatutRemuneration::Brouillon
            && $lot->cree_par_id !== $acteur->id
            && $acteur->can(Droit::EpargneRemuneration->value);
    }
}
