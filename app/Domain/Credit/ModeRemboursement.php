<?php

declare(strict_types=1);

namespace App\Domain\Credit;

enum ModeRemboursement: string
{
    case Especes = 'especes'; // à la caisse, session ouverte
    case Epargne = 'epargne'; // débit du compte d'épargne du prêt

    public function libelle(): string
    {
        return match ($this) {
            self::Especes => 'Espèces',
            self::Epargne => 'Épargne',
        };
    }
}
