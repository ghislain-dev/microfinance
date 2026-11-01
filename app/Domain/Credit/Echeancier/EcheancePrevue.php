<?php

declare(strict_types=1);

namespace App\Domain\Credit\Echeancier;

use App\Support\Montant\Montant;
use Carbon\CarbonImmutable;

final readonly class EcheancePrevue
{
    public function __construct(
        public int $rang,
        public CarbonImmutable $date,
        public Montant $capital,
        public Montant $interet,
    ) {}

    public function total(): Montant
    {
        return $this->capital->plus($this->interet);
    }
}
