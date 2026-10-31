<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\Compte;
use App\Support\Montant\Montant;

final readonly class EcartSolde
{
    public function __construct(
        public Compte $compte,
        public Montant $cache,
        public Montant $reel,
    ) {}
}
