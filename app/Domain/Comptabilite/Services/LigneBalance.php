<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Services;

use App\Support\Montant\Montant;

/** Ligne de balance : soldes signés (positif = débiteur, négatif = créditeur). */
final readonly class LigneBalance
{
    public function __construct(
        public string $rubrique,
        public string $syscohada,
        public string $libelle,
        public Montant $ouverture,
        public Montant $debit,
        public Montant $credit,
        public Montant $cloture,
    ) {}
}
