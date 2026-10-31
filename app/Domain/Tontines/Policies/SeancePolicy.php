<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Policies;

use App\Domain\Organisation\Acces\Droit;
use App\Domain\Tontines\Models\Seance;
use App\Models\User;

class SeancePolicy
{
    public function view(User $acteur, Seance $seance): bool
    {
        return $seance->organisation_id === $acteur->organisation_id && $acteur->can(Droit::MembresVoir->value);
    }

    /**
     * Saisie d'une séance : encaissement en espèces, donc tenue de caisse (caissier, gérant).
     */
    public function saisir(User $acteur, Seance $seance): bool
    {
        return $seance->organisation_id === $acteur->organisation_id && $acteur->can(Droit::CaisseOperer->value);
    }
}
