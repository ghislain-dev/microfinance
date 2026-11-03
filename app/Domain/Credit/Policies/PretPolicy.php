<?php

declare(strict_types=1);

namespace App\Domain\Credit\Policies;

use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\ProduitCredit;
use App\Domain\Credit\StatutPret;
use App\Domain\Membres\Models\Membre;
use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

/**
 * Séparation des tâches (skill §6, ADR 021) : l'auteur de la demande n'approuve ni ne décaisse, et le gérant
 * n'approuve pas un prêt qu'il a déjà approuvé au comité.
 */
class PretPolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->can(Droit::MembresVoir->value);
    }

    public function view(User $acteur, Pret $pret): bool
    {
        return $pret->organisation_id === $acteur->organisation_id && $acteur->can(Droit::MembresVoir->value);
    }

    public function create(User $acteur, ?Membre $membre = null, ?ProduitCredit $produit = null): bool
    {
        return ($membre === null || $membre->organisation_id === $acteur->organisation_id)
            && ($produit === null || $produit->organisation_id === $acteur->organisation_id)
            && $acteur->can(Droit::CreditSaisir->value);
    }

    public function analyser(User $acteur, Pret $pret): bool
    {
        return $pret->organisation_id === $acteur->organisation_id
            && $pret->statut === StatutPret::EnAnalyse
            && $acteur->can(Droit::CreditSaisir->value);
    }

    public function decider(User $acteur, Pret $pret): bool
    {
        if ($pret->organisation_id !== $acteur->organisation_id || $pret->saisi_par_id === $acteur->id) {
            return false;
        }

        return match ($pret->statut) {
            StatutPret::Soumis => $acteur->can(Droit::CreditApprouverComite->value),
            StatutPret::AttenteGerant => $pret->comite_par_id !== $acteur->id && $acteur->can(Droit::CreditApprouverGerant->value),
            default => false,
        };
    }

    public function decaisser(User $acteur, Pret $pret): bool
    {
        return $pret->organisation_id === $acteur->organisation_id
            && $pret->statut === StatutPret::Approuve
            && $pret->saisi_par_id !== $acteur->id
            && $acteur->can(Droit::CreditDecaisser->value);
    }

    /**
     * Remboursement : caissier ou gérant (tenue de caisse), en espèces ou depuis l'épargne.
     */
    public function rembourser(User $acteur, Pret $pret): bool
    {
        return $pret->organisation_id === $acteur->organisation_id && $acteur->can(Droit::CaisseOperer->value);
    }

    /**
     * Remboursement encaissé par le collecteur sur le terrain.
     */
    public function collecter(User $acteur, Pret $pret): bool
    {
        return $pret->organisation_id === $acteur->organisation_id && $acteur->can(Droit::CollecteSaisir->value);
    }
}
