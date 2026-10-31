<?php

declare(strict_types=1);

namespace App\Domain\Finance\Comptes;

/**
 * Plan de comptes simplifié (décision du 2026-10-31) ; correspondance SYSCOHADA en Phase 6.
 * Natures reprises des schémas d'opérations du skill finance-cooperative (§4).
 */
enum NatureCompte: string
{
    case Caisse = 'caisse';
    case Epargne = 'epargne';
    case PartsSociales = 'parts_sociales';
    case CycleTontine = 'cycle_tontine';
    case FondsSocial = 'fonds_social';
    case Pret = 'pret';
    case CreanceMembre = 'creance_membre';
    case Produit = 'produit';
    case Charge = 'charge';

    /**
     * Sens dans lequel le solde est positif : débit pour un actif ou une charge, crédit pour une dette
     * envers le membre, un fonds ou un produit.
     */
    public function sens(): Sens
    {
        return match ($this) {
            self::Caisse, self::Pret, self::CreanceMembre, self::Charge => Sens::Debit,
            self::Epargne, self::PartsSociales, self::CycleTontine, self::FondsSocial, self::Produit => Sens::Credit,
        };
    }

    /**
     * Caisse, épargne et parts ne passent jamais sous zéro (décision du 2026-10-31).
     */
    public function peutEtreNegatif(): bool
    {
        return ! in_array($this, [self::Caisse, self::Epargne, self::PartsSociales], true);
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Caisse => 'Caisse',
            self::Epargne => 'Épargne',
            self::PartsSociales => 'Parts sociales',
            self::CycleTontine => 'Cycle de tontine',
            self::FondsSocial => 'Fonds social',
            self::Pret => 'Prêt',
            self::CreanceMembre => 'Créance sur membre',
            self::Produit => 'Produit',
            self::Charge => 'Charge',
        };
    }
}
