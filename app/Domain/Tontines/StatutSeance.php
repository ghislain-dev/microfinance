<?php

declare(strict_types=1);

namespace App\Domain\Tontines;

enum StatutSeance: string
{
    case Prevue = 'prevue';
    case Tenue = 'tenue';

    public function libelle(): string
    {
        return $this === self::Prevue ? 'Prévue' : 'Tenue';
    }
}
