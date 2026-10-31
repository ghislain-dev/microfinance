<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Remuneration;

enum StatutRemuneration: string
{
    case Brouillon = 'brouillon';
    case Validee = 'validee';
    case Rejetee = 'rejetee';

    public function libelle(): string
    {
        return match ($this) {
            self::Brouillon => 'À valider',
            self::Validee => 'Versée',
            self::Rejetee => 'Rejetée',
        };
    }
}
