<?php

declare(strict_types=1);

namespace App\Domain\Membres\Exceptions;

use App\Domain\Membres\Models\Membre;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Avertissement (pas un refus) : le téléphone est déjà celui d'autres membres ; à confirmer.
 */
final class TelephoneDejaUtilise extends DomainException
{
    /**
     * @param  Collection<int, Membre>  $membres
     */
    public function __construct(public readonly Collection $membres)
    {
        parent::__construct('Ce téléphone est déjà utilisé par : '.$membres->pluck('numero')->implode(', ').'.');
    }
}
