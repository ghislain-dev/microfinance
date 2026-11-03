<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Actions;

use App\Domain\Caisse\Models\Caisse;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Exceptions\ValidationRequise;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\DemandeDecaissement;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\Services\RegleEpargne;
use App\Domain\Epargne\StatutDemande;
use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Montant;
use Illuminate\Support\Facades\DB;

/**
 * Retrait en espèces : débit épargne (montant + frais), crédit caisse, frais en produit.
 * Au-delà du seuil : demande à valider, puis paiement au guichet avec la demande approuvée.
 */
final class Retirer
{
    public function __construct(
        private readonly MoteurFinancier $moteur,
        private readonly RegleEpargne $regles,
        private readonly OuvrirCompte $ouvrirCompte,
    ) {}

    /**
     * @param  Caisse|null  $caisseCollecteur  retrait payé sur le terrain par le collecteur (ADR 022) : jamais au-delà du seuil
     */
    public function executer(User $operateur, CompteEpargne $compte, Montant $montant, string $cle, ?DemandeDecaissement $demande = null,
        ?Caisse $caisseCollecteur = null): MouvementEpargne
    {
        $compte->loadMissing(['membre', 'type', 'compte']);
        AutoriserDansOrganisation::autoriser($operateur, $caisseCollecteur === null ? 'operer' : 'collecter', $compte);
        $session = $caisseCollecteur === null ? $this->regles->sessionOuverte($operateur) : null;
        $caisse = $caisseCollecteur ?? $session->caisse ?? throw new CompteEpargneRefuse('Caisse introuvable.');
        $this->regles->exigerDevise($compte, $montant);
        $this->regles->exigerNonBloque($compte);

        if (($deja = $this->moteur->dejaEnregistree($cle)) !== null) {
            return $this->regles->enregistrerMouvement($deja->id, $compte, 'retrait', $montant, session: $session);
        }
        if ($demande !== null) {
            $this->regles->exigerDemandeApprouvee($demande, 'retrait', $compte, $montant);
        } elseif ($caisseCollecteur !== null && $this->regles->depasseSeuil($montant)) {
            throw new CompteEpargneRefuse('Retrait au-delà du seuil de validation : le membre passe au guichet.');
        } elseif ($this->regles->depasseSeuil($montant)) {
            throw new ValidationRequise($this->regles->creerDemande($operateur, 'retrait', $compte, $montant));
        }

        $frais = $compte->type->frais_retrait;

        return DB::transaction(function () use ($compte, $montant, $frais, $cle, $session, $demande, $caisse) {
            // Verrou du compte d'épargne avant de juger le solde minimum (le moteur reverrouille sans risque).
            Compte::query()->whereKey($compte->compte_id)->lockForUpdate()->firstOrFail();
            $this->regles->exigerSoldeMinimum($this->moteur->soldeReel($compte->compte)->moins($montant)->moins($frais), $compte);

            $lignes = [
                LigneEcriture::debit($compte->compte, $montant->plus($frais)),
                LigneEcriture::credit($caisse->compte($montant->devise), $montant),
            ];
            if ($frais->estPositif()) {
                $produit = $this->ouvrirCompte->obtenir("PRODUIT-FRAIS-RETRAIT-{$frais->devise->value}",
                    "Frais de retrait ({$frais->devise->value})", NatureCompte::Produit, $frais->devise);
                $lignes[] = LigneEcriture::credit($produit, $frais);
            }

            $operation = $this->moteur->executer($cle, 'retrait', $lignes, validateur: $demande?->decidePar);
            if ($demande !== null) {
                $demande->forceFill(['statut' => StatutDemande::Executee, 'operation_id' => $operation->id])->save();
            }

            return $this->regles->enregistrerMouvement($operation->id, $compte, 'retrait', $montant, $frais, $session);
        });
    }
}
