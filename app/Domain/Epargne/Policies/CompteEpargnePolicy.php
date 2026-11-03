<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Policies;

use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\TypeCompteEpargne;
use App\Domain\Membres\Models\Membre;
use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

class CompteEpargnePolicy
{
    public function view(User $acteur, CompteEpargne $compte): bool
    {
        return $compte->organisation_id === $acteur->organisation_id && $acteur->can(Droit::MembresVoir->value);
    }

    /**
     * Dépôt et retrait en espèces : caissier ou gérant (tenue de caisse).
     */
    public function operer(User $acteur, CompteEpargne $compte): bool
    {
        return $compte->organisation_id === $acteur->organisation_id && $acteur->can(Droit::CaisseOperer->value);
    }

    /**
     * Dépôt ou retrait collecté sur le terrain (la tournée est vérifiée par la synchronisation).
     */
    public function collecter(User $acteur, CompteEpargne $compte): bool
    {
        return $compte->organisation_id === $acteur->organisation_id && $acteur->can(Droit::CollecteSaisir->value);
    }

    public function virer(User $acteur, CompteEpargne $source, CompteEpargne $destination): bool
    {
        return $source->organisation_id === $acteur->organisation_id
            && $destination->organisation_id === $acteur->organisation_id
            && $acteur->can(Droit::EpargneVirer->value);
    }

    /**
     * Ouvrir un compte : admin, gérant ou caissier ; membre et type de son organisation.
     */
    public function create(User $acteur, ?Membre $membre = null, ?TypeCompteEpargne $type = null): bool
    {
        return ($membre === null || $membre->organisation_id === $acteur->organisation_id)
            && ($type === null || $type->organisation_id === $acteur->organisation_id)
            && $acteur->can(Droit::EpargneOuvrirCompte->value);
    }
}
