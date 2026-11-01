<?php

declare(strict_types=1);

namespace App\Domain\Credit\Policies;

use App\Domain\Credit\Models\ProduitCredit;
use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

class ProduitCreditPolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->can(Droit::CreditParametrer->value);
    }

    public function create(User $acteur): bool
    {
        return $acteur->can(Droit::CreditParametrer->value);
    }

    public function update(User $acteur, ProduitCredit $produit): bool
    {
        return $produit->organisation_id === $acteur->organisation_id && $acteur->can(Droit::CreditParametrer->value);
    }
}
