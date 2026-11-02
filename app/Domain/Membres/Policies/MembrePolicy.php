<?php

declare(strict_types=1);

namespace App\Domain\Membres\Policies;

use App\Domain\Membres\Models\Membre;
use App\Domain\Organisation\Acces\Droit;
use App\Domain\Organisation\Models\Agence;
use App\Models\User;

class MembrePolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->can(Droit::MembresVoir->value);
    }

    public function view(User $acteur, Membre $membre): bool
    {
        return $acteur->organisation_id === $membre->organisation_id && $acteur->can(Droit::MembresVoir->value);
    }

    /**
     * Sans agence : accès à l'écran d'inscription ; avec agence : inscription dans cette agence.
     */
    public function create(User $acteur, ?Agence $agence = null): bool
    {
        return ($agence === null || $acteur->organisation_id === $agence->organisation_id)
            && $acteur->can(Droit::MembresGerer->value);
    }

    public function update(User $acteur, Membre $membre): bool
    {
        return $acteur->organisation_id === $membre->organisation_id && $acteur->can(Droit::MembresGerer->value);
    }

    public function changerStatut(User $acteur, Membre $membre): bool
    {
        return $this->update($acteur, $membre);
    }

    /**
     * Accès à l'application mobile : gérant ou caissier, au guichet (ADR 022).
     */
    public function accesMobile(User $acteur, Membre $membre): bool
    {
        return $acteur->organisation_id === $membre->organisation_id && $acteur->can(Droit::MembresAccesMobile->value);
    }
}
