<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Policies;

use App\Domain\Organisation\Acces\Droit;
use App\Domain\Tontines\Models\Aide;
use App\Models\User;

class AidePolicy
{
    /**
     * Comité, jamais la personne qui a saisi la demande.
     */
    public function decider(User $acteur, Aide $aide): bool
    {
        return $aide->organisation_id === $acteur->organisation_id
            && $aide->getAttribute('statut') === 'en_attente'
            && $aide->getAttribute('demande_par_id') !== $acteur->id
            && $acteur->can(Droit::TontinesValider->value);
    }
}
