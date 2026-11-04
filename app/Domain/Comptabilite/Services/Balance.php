<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Services;

use App\Support\Montant\Devise;
use App\Support\Montant\Montant;

final readonly class Balance
{
    /**
     * @param  list<LigneBalance>  $lignes
     */
    public function __construct(public Devise $devise, public array $lignes, public Montant $totalDebit, public Montant $totalCredit) {}
}
