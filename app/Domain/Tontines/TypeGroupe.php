<?php

declare(strict_types=1);

namespace App\Domain\Tontines;

enum TypeGroupe: string
{
    case Rotative = 'rotative';
    case Avec = 'avec';

    public function libelle(): string
    {
        return match ($this) {
            self::Rotative => 'Rotative',
            self::Avec => 'AVEC (épargne cumulative)',
        };
    }
}
