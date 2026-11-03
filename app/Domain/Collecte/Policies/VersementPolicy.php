<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Policies;

use App\Domain\Collecte\Models\VersementCollecte;
use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

/**
 * Versement au guichet : le caissier (ou gérant) en session le reçoit ; un écart est validé par le gérant,
 * jamais par le caissier qui a compté ni par le collecteur (4 yeux).
 */
class VersementPolicy
{
    public function recevoir(User $acteur): bool
    {
        return $acteur->can(Droit::CaisseOperer->value);
    }

    public function valider(User $acteur, VersementCollecte $versement): bool
    {
        return $versement->organisation_id === $acteur->organisation_id
            && $versement->statut === 'ecart_a_justifier'
            && $versement->getAttribute('caissier_id') !== $acteur->id
            && $versement->getAttribute('collecteur_id') !== $acteur->id
            && $acteur->can(Droit::CaisseValiderEcart->value);
    }
}
