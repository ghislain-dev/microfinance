<?php

declare(strict_types=1);

namespace App\Domain\Membres;

/**
 * Statuts validés le 2026-10-31.
 */
enum StatutMembre: string
{
    case Actif = 'actif';
    case Suspendu = 'suspendu';
    case Demissionnaire = 'demissionnaire';
    case Exclu = 'exclu';
    case Decede = 'decede';

    public function libelle(): string
    {
        return match ($this) {
            self::Actif => 'Actif',
            self::Suspendu => 'Suspendu',
            self::Demissionnaire => 'Démissionnaire',
            self::Exclu => 'Exclu',
            self::Decede => 'Décédé',
        };
    }
}
