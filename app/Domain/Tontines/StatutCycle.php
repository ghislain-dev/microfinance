<?php

declare(strict_types=1);

namespace App\Domain\Tontines;

enum StatutCycle: string
{
    case EnPreparation = 'en_preparation';
    case EnCours = 'en_cours';
    case Cloture = 'cloture';

    public function libelle(): string
    {
        return match ($this) {
            self::EnPreparation => 'En préparation',
            self::EnCours => 'En cours',
            self::Cloture => 'Clôturé',
        };
    }
}
