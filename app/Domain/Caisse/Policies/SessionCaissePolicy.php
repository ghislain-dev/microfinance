<?php

declare(strict_types=1);

namespace App\Domain\Caisse\Policies;

use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

class SessionCaissePolicy
{
    public function cloturer(User $acteur, SessionCaisse $session): bool
    {
        return $session->caissier_id === $acteur->id && $acteur->can(Droit::CaisseOperer->value);
    }

    /**
     * 4 yeux : le gérant valide, jamais l'écart de sa propre session.
     */
    public function validerEcart(User $acteur, SessionCaisse $session): bool
    {
        return $session->organisation_id === $acteur->organisation_id
            && $session->caissier_id !== $acteur->id
            && $acteur->can(Droit::CaisseValiderEcart->value);
    }
}
