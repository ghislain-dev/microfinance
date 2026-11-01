<?php

declare(strict_types=1);

namespace App\Domain\Credit;

/**
 * Circuit d'un prêt (2026-11-01) : saisie et analyse → comité → gérant au-delà du seuil → décaissement → soldé.
 */
enum StatutPret: string
{
    case EnAnalyse = 'en_analyse';
    case Soumis = 'soumis';                // analysé, attend le comité
    case AttenteGerant = 'attente_gerant'; // approuvé par le comité, au-delà du seuil
    case Approuve = 'approuve';            // prêt à décaisser
    case Rejete = 'rejete';
    case EnCours = 'en_cours';
    case Solde = 'solde';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAnalyse => 'En analyse',
            self::Soumis => 'Au comité',
            self::AttenteGerant => 'Au gérant',
            self::Approuve => 'Approuvé',
            self::Rejete => 'Rejeté',
            self::EnCours => 'En cours',
            self::Solde => 'Soldé',
        };
    }

    public function couleur(): string
    {
        return match ($this) {
            self::EnAnalyse, self::Soumis, self::AttenteGerant => 'warning',
            self::Approuve => 'info',
            self::Rejete => 'danger',
            self::EnCours => 'primary',
            self::Solde => 'success',
        };
    }
}
