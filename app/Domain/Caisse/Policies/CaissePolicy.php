<?php

declare(strict_types=1);

namespace App\Domain\Caisse\Policies;

use App\Domain\Caisse\Models\Caisse;
use App\Domain\Organisation\Acces\Droit;
use App\Domain\Organisation\Models\Agence;
use App\Models\User;

class CaissePolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->canAny([Droit::CaissesGerer->value, Droit::CaisseOperer->value, Droit::CaisseValiderEcart->value]);
    }

    public function view(User $acteur, Caisse $caisse): bool
    {
        return $caisse->organisation_id === $acteur->organisation_id && $this->viewAny($acteur);
    }

    public function create(User $acteur, ?Agence $agence = null): bool
    {
        return ($agence === null || $agence->organisation_id === $acteur->organisation_id)
            && $acteur->can(Droit::CaissesGerer->value);
    }

    /**
     * Tenir la caisse : caissier ou gérant, rattaché à l'agence de la caisse (2026-10-31).
     */
    public function operer(User $acteur, Caisse $caisse): bool
    {
        return $caisse->organisation_id === $acteur->organisation_id
            && $caisse->actif
            && $caisse->getAttribute('collecteur_id') === null // la caisse d'un collecteur ne s'ouvre pas au guichet
            && $acteur->can(Droit::CaisseOperer->value)
            && $acteur->agences()->whereKey($caisse->agence_id)->exists();
    }
}
