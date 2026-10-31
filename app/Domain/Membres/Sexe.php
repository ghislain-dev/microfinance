<?php

declare(strict_types=1);

namespace App\Domain\Membres;

enum Sexe: string
{
    case Masculin = 'M';
    case Feminin = 'F';

    public function libelle(): string
    {
        return $this === self::Masculin ? 'Masculin' : 'Féminin';
    }
}
