<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Policies;

use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->can(Droit::UtilisateursVoir->value);
    }

    public function view(User $acteur, User $cible): bool
    {
        return $this->memeOrganisation($acteur, $cible)
            && $acteur->can(Droit::UtilisateursVoir->value);
    }

    public function create(User $acteur): bool
    {
        return $acteur->can(Droit::UtilisateursGerer->value);
    }

    public function update(User $acteur, User $cible): bool
    {
        return $this->memeOrganisation($acteur, $cible)
            && $acteur->can(Droit::UtilisateursGerer->value);
    }

    /**
     * Un admin ne modifie jamais ses propres rôles (validé le 2026-10-31).
     */
    public function modifierRoles(User $acteur, User $cible): bool
    {
        return $acteur->id !== $cible->id && $this->update($acteur, $cible);
    }

    /**
     * Un admin ne désactive jamais son propre compte (validé le 2026-10-31).
     */
    public function desactiver(User $acteur, User $cible): bool
    {
        return $acteur->id !== $cible->id && $this->update($acteur, $cible);
    }

    private function memeOrganisation(User $acteur, User $cible): bool
    {
        return $acteur->organisation_id === $cible->organisation_id;
    }
}
