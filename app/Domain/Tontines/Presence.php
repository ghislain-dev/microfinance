<?php

declare(strict_types=1);

namespace App\Domain\Tontines;

enum Presence: string
{
    case Present = 'present';
    case Retard = 'retard';
    case Absent = 'absent';

    public function libelle(): string
    {
        return match ($this) {
            self::Present => 'Présent',
            self::Retard => 'En retard',
            self::Absent => 'Absent',
        };
    }
}
