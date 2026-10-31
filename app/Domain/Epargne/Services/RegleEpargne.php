<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Services;

use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Caisse\StatutSession;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\DemandeDecaissement;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\Models\SeuilValidation;
use App\Domain\Epargne\StatutDemande;
use App\Domain\Membres\StatutMembre;
use App\Models\User;
use App\Support\Montant\Montant;

/**
 * Règles communes aux opérations d'épargne (décisions du 2026-10-31).
 */
final class RegleEpargne
{
    /**
     * Toute opération en espèces exige une session de caisse ouverte de l'opérateur (skill §7).
     */
    public function sessionOuverte(User $operateur): SessionCaisse
    {
        return SessionCaisse::query()->with('caisse')
            ->where('caissier_id', $operateur->id)->where('statut', StatutSession::Ouverte->value)->first()
            ?? throw new CompteEpargneRefuse('Aucune session de caisse ouverte : ouvrez votre caisse avant toute opération en espèces.');
    }

    /**
     * Membre actif : toute opération ; sinon retraits seulement.
     */
    public function exigerMembreActif(CompteEpargne $compte, string $operation): void
    {
        $statut = $compte->membre->statut;
        if ($statut !== StatutMembre::Actif) {
            throw new CompteEpargneRefuse("Membre {$statut->libelle()} ({$compte->numero}) : {$operation} impossible, retraits seulement.");
        }
    }

    public function exigerDevise(CompteEpargne $compte, Montant $montant): void
    {
        if ($montant->devise !== $compte->type->devise || ! $montant->estPositif()) {
            throw new CompteEpargneRefuse("Montant attendu : positif, en {$compte->type->devise->value}.");
        }
    }

    public function exigerNonBloque(CompteEpargne $compte): void
    {
        if ($compte->estBloque()) {
            throw new CompteEpargneRefuse("Compte {$compte->numero} bloqué jusqu'au {$compte->bloque_jusqu_au?->format('d/m/Y')}.");
        }
    }

    public function exigerSoldeMinimum(Montant $soldeApres, CompteEpargne $compte): void
    {
        if ($soldeApres->comparer($compte->type->solde_minimum) < 0) {
            throw new CompteEpargneRefuse("Opération refusée : le solde minimum de {$compte->type->solde_minimum->formate()} doit rester sur le compte.");
        }
    }

    public function depasseSeuil(Montant $montant): bool
    {
        $seuil = SeuilValidation::query()->where('devise', $montant->devise->value)->first();

        return $seuil !== null && $montant->comparer($seuil->montant) > 0;
    }

    /**
     * Demande approuvée correspondant exactement à l'opération ; sinon refus.
     */
    public function exigerDemandeApprouvee(DemandeDecaissement $demande, string $type, CompteEpargne $compte, Montant $montant): void
    {
        if ($demande->statut !== StatutDemande::Approuvee || $demande->type !== $type
            || $demande->compte_epargne_id !== $compte->id || ! $demande->montant->egal($montant)) {
            throw new CompteEpargneRefuse("La demande n° {$demande->id} n'autorise pas cette opération ({$demande->statut->libelle()}).");
        }
    }

    public function creerDemande(User $demandeur, string $type, CompteEpargne $compte, Montant $montant, ?CompteEpargne $destination = null): DemandeDecaissement
    {
        $demande = new DemandeDecaissement;
        $demande->forceFill([
            'organisation_id' => $compte->organisation_id,
            'type' => $type,
            'compte_epargne_id' => $compte->id,
            'destination_compte_epargne_id' => $destination?->id,
            'devise' => $montant->devise, // avant le montant : MontantCast lit la devise
            'montant' => $montant,
            'demandeur_id' => $demandeur->id,
            'statut' => StatutDemande::EnAttente,
        ])->save();

        return $demande;
    }

    public function enregistrerMouvement(int $operationId, CompteEpargne $compte, string $type, Montant $montant, ?Montant $frais = null, ?SessionCaisse $session = null): MouvementEpargne
    {
        $existant = MouvementEpargne::query()->where('operation_id', $operationId)->where('compte_epargne_id', $compte->id)->first();
        if ($existant !== null) {
            return $existant; // opération rejouée (même clé) : la ligne existe déjà
        }

        $mouvement = new MouvementEpargne;
        $mouvement->forceFill([
            'organisation_id' => $compte->organisation_id,
            'compte_epargne_id' => $compte->id,
            'operation_id' => $operationId,
            'type' => $type,
            'devise' => $montant->devise,
            'montant' => $montant,
            'frais' => $frais ?? Montant::zero($montant->devise),
            'session_caisse_id' => $session?->id,
            'auteur_id' => auth()->id(),
        ])->save();

        return $mouvement;
    }
}
