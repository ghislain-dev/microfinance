<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Finance\Exceptions\OperationDesequilibree;
use App\Domain\Finance\Models\Compte;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;

/**
 * Une ligne d'une opération à confier au MoteurFinancier : un débit OU un crédit, en centimes.
 */
final readonly class LigneEcriture
{
    public function __construct(
        public int $compteId,
        public int $debit,
        public int $credit,
        public Devise $devise,
    ) {
        if ($debit < 0 || $credit < 0 || ($debit > 0) === ($credit > 0)) {
            throw new OperationDesequilibree('Une ligne porte soit un débit, soit un crédit, strictement positif.');
        }
    }

    public static function debit(Compte|int $compte, Montant $montant): self
    {
        return new self($compte instanceof Compte ? $compte->id : $compte, $montant->centimes, 0, $montant->devise);
    }

    public static function credit(Compte|int $compte, Montant $montant): self
    {
        return new self($compte instanceof Compte ? $compte->id : $compte, 0, $montant->centimes, $montant->devise);
    }
}
