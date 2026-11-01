<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Policies;

use App\Domain\Organisation\Acces\Droit;
use App\Domain\Tontines\Models\Partage;
use App\Models\User;

class PartagePolicy
{
    /**
     * Validation du comité (skill §5), jamais par la personne qui a calculé le partage.
     */
    public function decider(User $acteur, Partage $partage): bool
    {
        return $partage->organisation_id === $acteur->organisation_id
            && $partage->getAttribute('statut') === 'brouillon'
            && $partage->getAttribute('calcule_par_id') !== $acteur->id
            && $acteur->can(Droit::TontinesValider->value);
    }
}
