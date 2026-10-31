<?php

declare(strict_types=1);

namespace App\Domain\Caisse;

enum StatutSession: string
{
    case OuvertureAValider = 'ouverture_a_valider'; // écart au comptage d'ouverture
    case Ouverte = 'ouverte';
    case EcartAJustifier = 'ecart_a_justifier';     // écart au comptage de clôture
    case Fermee = 'fermee';

    public function libelle(): string
    {
        return match ($this) {
            self::OuvertureAValider => 'Écart à l’ouverture, à valider',
            self::Ouverte => 'Ouverte',
            self::EcartAJustifier => 'Écart à la clôture, à justifier',
            self::Fermee => 'Fermée',
        };
    }

    public function attendValidation(): bool
    {
        return $this === self::OuvertureAValider || $this === self::EcartAJustifier;
    }
}
