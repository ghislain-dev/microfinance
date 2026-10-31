<?php

declare(strict_types=1);

namespace App\Support\Montant;

/**
 * Devises gérées par la plateforme. Toutes sont stockées en unité mineure (1/100).
 */
enum Devise: string
{
    case USD = 'USD';
    case CDF = 'CDF';
}
