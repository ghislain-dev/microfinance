<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Services;

use App\Support\Montant\Montant;
use Carbon\CarbonImmutable;

final readonly class LigneGrandLivre
{
    public function __construct(
        public CarbonImmutable $date,
        public int $operationId,
        public string $type,
        public ?string $motif,
        public string $compte,
        public Montant $debit,
        public Montant $credit,
        public Montant $solde,
    ) {}
}
