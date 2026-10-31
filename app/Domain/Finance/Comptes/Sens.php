<?php

declare(strict_types=1);

namespace App\Domain\Finance\Comptes;

enum Sens: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
