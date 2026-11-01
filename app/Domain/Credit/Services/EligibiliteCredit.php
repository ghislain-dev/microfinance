<?php

declare(strict_types=1);

namespace App\Domain\Credit\Services;

use App\Domain\Credit\CreditRefuse;
use App\Domain\Credit\Models\ProduitCredit;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Models\Membre;
use App\Domain\Membres\StatutMembre;
use App\Support\Montant\Montant;

/**
 * Conditions d'accès au crédit (ADR 021), vérifiées à la demande puis au décaissement.
 */
final class EligibiliteCredit
{
    public function __construct(private readonly MoteurFinancier $moteur) {}

    public function exigerMembreEtCompte(Membre $membre, CompteEpargne $compte, ProduitCredit $produit): void
    {
        if ($membre->statut !== StatutMembre::Actif) {
            throw new CreditRefuse("Membre {$membre->statut->libelle()} : crédit impossible.");
        }
        $compte->loadMissing('type');
        if ($compte->membre_id !== $membre->id || $compte->type->devise !== $produit->devise) {
            throw new CreditRefuse("Le prêt est versé sur un compte d'épargne du membre en {$produit->devise->value}.");
        }
    }

    /**
     * Montant ≤ N × épargne du membre dans la devise du prêt (somme des soldes réels).
     */
    public function exigerPlafond(Membre $membre, ProduitCredit $produit, Montant $montant): void
    {
        $epargne = Montant::zero($produit->devise);
        $comptes = CompteEpargne::query()->with('compte')->where('membre_id', $membre->id)
            ->whereHas('type', fn ($q) => $q->where('devise', $produit->devise->value))->get();
        foreach ($comptes as $compte) {
            $epargne = $epargne->plus($this->moteur->soldeReel($compte->compte));
        }

        $plafond = new Montant($epargne->centimes * $produit->multiple_epargne, $produit->devise);
        if ($montant->comparer($plafond) > 0) {
            throw new CreditRefuse("Montant au-delà du plafond de {$plafond->formate()} ({$produit->multiple_epargne} × l'épargne de {$epargne->formate()}).");
        }
    }
}
