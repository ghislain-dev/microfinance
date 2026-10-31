<?php

declare(strict_types=1);

namespace App\Domain\Tontines;

enum ModeTours: string
{
    case Tirage = 'tirage';
    case OrdreFixe = 'ordre_fixe';
    case Enchere = 'enchere';

    public function libelle(): string
    {
        return match ($this) {
            self::Tirage => 'Tirage au sort',
            self::OrdreFixe => 'Ordre fixé',
            self::Enchere => 'Enchère',
        };
    }
}
