<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Acces;

/**
 * Rôles communs à toutes les organisations ; leur attribution est propre à chaque organisation
 * (spatie « teams » sur organisation_id). Matrice validée le 2026-10-31 pour la Phase 0.
 */
enum Role: string
{
    case Admin = 'admin';
    case Gerant = 'gerant';
    case Comptable = 'comptable';
    case Caissier = 'caissier';
    case Collecteur = 'collecteur';
    case AgentCredit = 'agent_credit';
    case Comite = 'comite';
    case Membre = 'membre'; // aucun accès web : application mobile uniquement

    public function libelle(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Gerant => 'Gérant',
            self::Comptable => 'Comptable',
            self::Caissier => 'Caissier',
            self::Collecteur => 'Collecteur',
            self::AgentCredit => 'Agent de crédit',
            self::Comite => 'Comité',
            self::Membre => 'Membre',
        };
    }

    /**
     * @return list<Droit>
     */
    public function droits(): array
    {
        return match ($this) {
            // Pas de validation d'extourne pour l'admin (décision du 2026-10-31).
            self::Admin => [
                Droit::TableauDeBordVoir, Droit::OrganisationModifier, Droit::AgencesVoir, Droit::AgencesGerer,
                Droit::UtilisateursVoir, Droit::UtilisateursGerer, Droit::AuditVoir,
            ],
            self::Gerant => [Droit::TableauDeBordVoir, Droit::AgencesVoir, Droit::UtilisateursVoir, Droit::AuditVoir, Droit::OperationsExtourner],
            self::Comptable => [Droit::TableauDeBordVoir, Droit::AgencesVoir, Droit::AuditVoir, Droit::OperationsExtourner],
            self::Caissier, self::Collecteur, self::AgentCredit, self::Comite => [Droit::TableauDeBordVoir],
            self::Membre => [],
        };
    }
}
