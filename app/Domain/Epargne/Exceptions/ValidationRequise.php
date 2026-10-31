<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Exceptions;

use App\Domain\Epargne\Models\DemandeDecaissement;
use DomainException;

/**
 * Montant au-delà du seuil : une demande a été créée, rien n'a été décaissé.
 */
final class ValidationRequise extends DomainException
{
    public function __construct(public readonly DemandeDecaissement $demande)
    {
        parent::__construct("Montant au-delà du seuil : demande n° {$demande->id} transmise pour validation.");
    }
}
