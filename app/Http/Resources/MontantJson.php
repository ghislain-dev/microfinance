<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Montant\Montant;

/**
 * Montant dans l'API : entier de centimes + devise (jamais de nombre à virgule) et texte formaté.
 */
final class MontantJson
{
    /**
     * @return array{centimes: int, devise: string, texte: string}|null
     */
    public static function de(?Montant $montant): ?array
    {
        return $montant === null ? null
            : ['centimes' => $montant->centimes, 'devise' => $montant->devise->value, 'texte' => $montant->formate()];
    }
}
