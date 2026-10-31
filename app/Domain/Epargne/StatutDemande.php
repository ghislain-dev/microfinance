<?php

declare(strict_types=1);

namespace App\Domain\Epargne;

enum StatutDemande: string
{
    case EnAttente = 'en_attente';
    case Approuvee = 'approuvee';
    case Rejetee = 'rejetee';
    case Executee = 'executee';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente de validation',
            self::Approuvee => 'Validée, à payer',
            self::Rejetee => 'Rejetée',
            self::Executee => 'Exécutée',
        };
    }
}
