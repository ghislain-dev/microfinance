<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Policies;

use App\Domain\Epargne\Models\DemandeDecaissement;
use App\Domain\Epargne\StatutDemande;
use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

class DemandeDecaissementPolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->can(Droit::OperationsValiderDecaissement->value);
    }

    /**
     * 4 yeux (CLAUDE.md §4.7) : gérant ou comptable, jamais le demandeur.
     */
    public function decider(User $acteur, DemandeDecaissement $demande): bool
    {
        return $demande->organisation_id === $acteur->organisation_id
            && $demande->demandeur_id !== $acteur->id
            && $demande->statut === StatutDemande::EnAttente
            && $acteur->can(Droit::OperationsValiderDecaissement->value);
    }
}
