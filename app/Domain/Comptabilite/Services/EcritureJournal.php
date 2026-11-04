<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Services;

use App\Support\Montant\Montant;

final readonly class EcritureJournal
{
    public function __construct(public string $compte, public string $syscohada, public Montant $debit, public Montant $credit) {}
}
