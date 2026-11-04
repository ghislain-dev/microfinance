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
    case CreancePersonnel = 'creance_personnel'; // manquants de caisse imputés au caissier (2026-10-31)
    case PositionChange = 'position_change';     // contrepartie des conversions USD/CDF, une par devise
    case Produit = 'produit';
    case FondsPropres = 'fonds_propres'; // ex. excédents à répartir (ristournes)
    case Resultat = 'resultat';          // résultat en instance d'affectation (ADR 025)
    case Charge = 'charge';

    /**
     * Sens dans lequel le solde est positif : débit pour un actif ou une charge, crédit pour une dette
     * envers le membre, un fonds ou un produit.
     */
    public function sens(): Sens
    {
        return match ($this) {
            self::Caisse, self::Pret, self::CreanceMembre, self::CreancePersonnel, self::PositionChange, self::Charge => Sens::Debit,
            self::Epargne, self::PartsSociales, self::CycleTontine, self::FondsSocial, self::Produit, self::FondsPropres, self::Resultat => Sens::Credit,
        };
    }

    /**
     * Caisse, épargne et parts ne passent jamais sous zéro (décision du 2026-10-31) ; les fonds propres non plus
     * (on ne distribue pas d'excédents qui n'existent pas).
     */
    public function peutEtreNegatif(): bool
    {
        return ! in_array($this, [self::Caisse, self::Epargne, self::PartsSociales, self::FondsPropres], true);
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
            self::CreancePersonnel => 'Créance sur le personnel',
            self::PositionChange => 'Position de change',
            self::Produit => 'Produit',
            self::FondsPropres => 'Fonds propres',
            self::Resultat => 'Résultat',
            self::Charge => 'Charge',
        };
    }
}
