<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Policies;

use App\Domain\Comptabilite\Models\AffectationResultat;
use App\Domain\Comptabilite\Models\DemandeCloture;
use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

/**
 * États comptables : comptable et gérant ; correspondance SYSCOHADA : comptable seul ;
 * clôture mensuelle : demandée par le comptable, validée par le gérant (ADR 023).
 */
class ComptabilitePolicy
{
    public function voir(User $acteur): bool
    {
        return $acteur->can(Droit::ComptaVoir->value);
    }

    public function parametrer(User $acteur): bool
    {
        return $acteur->can(Droit::ComptaParametrer->value);
    }

    public function cloturer(User $acteur): bool
    {
        return $acteur->can(Droit::ComptaCloturer->value);
    }

    public function validerClotures(User $acteur): bool
    {
        return $acteur->can(Droit::ComptaValiderCloture->value);
    }

    /**
     * Gérant, jamais l'auteur de la demande (4 yeux).
     */
    public function validerCloture(User $acteur, DemandeCloture $demande): bool
    {
        return $demande->organisation_id === $acteur->organisation_id
            && $demande->statut === 'en_attente'
            && $demande->demande_par_id !== $acteur->id
            && $acteur->can(Droit::ComptaValiderCloture->value);
    }

    /**
     * Affectation du résultat annuel (ADR 025) : proposée par le comptable…
     */
    public function affecter(User $acteur): bool
    {
        return $acteur->can(Droit::ComptaCloturer->value);
    }

    /**
     * … validée par le gérant, jamais l'auteur.
     */
    public function validerAffectation(User $acteur, AffectationResultat $affectation): bool
    {
        return $affectation->organisation_id === $acteur->organisation_id
            && $affectation->statut === 'en_attente'
            && $affectation->propose_par_id !== $acteur->id
            && $acteur->can(Droit::ComptaValiderCloture->value);
    }
}
