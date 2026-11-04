<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite;

use DomainException;

final class ClotureRefusee extends DomainException
{
    /**
     * @param  list<string>  $problemes  contrôles qui bloquent la clôture
     */
    public function __construct(string $message, public readonly array $problemes = [])
    {
        parent::__construct($problemes === [] ? $message : $message.' '.implode(' ', $problemes));
    }
}
