<?php

declare(strict_types=1);

namespace App\Domain\Membres\Exceptions;

use App\Domain\Membres\Models\Membre;
use DomainException;

final class PieceDejaEnregistree extends DomainException
{
    public function __construct(public readonly Membre $membreExistant)
    {
        parent::__construct("Cette pièce d'identité est déjà enregistrée pour le membre {$membreExistant->numero}.");
    }
}
