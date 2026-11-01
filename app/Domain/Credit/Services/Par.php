<?php

declare(strict_types=1);

namespace App\Domain\Credit\Services;

use App\Support\Montant\Montant;

/**
 * Portefeuille à risque : encours des prêts en retard de plus de N jours ÷ encours total.
 */
final readonly class Par
{
    public function __construct(
        public int $jours,
        public Montant $aRisque,
        public Montant $encours,
        public int $tauxPb, // points de base (5 556 = 55,56 %)
    ) {}
}
