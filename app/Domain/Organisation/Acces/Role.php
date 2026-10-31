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

    /**
     * @return list<Droit>
     */
    public function droits(): array
    {
        return match ($this) {
            self::Admin => Droit::cases(),
            self::Gerant => [Droit::TableauDeBordVoir, Droit::AgencesVoir, Droit::UtilisateursVoir, Droit::AuditVoir],
            self::Comptable => [Droit::TableauDeBordVoir, Droit::AgencesVoir, Droit::AuditVoir],
            self::Caissier, self::Collecteur, self::AgentCredit, self::Comite => [Droit::TableauDeBordVoir],
            self::Membre => [],
        };
    }
}
