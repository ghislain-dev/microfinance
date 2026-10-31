<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Numéros au format international, pour comparer « 0812345678 » et « +243 81 234 5678 ».
 * Un numéro national à 10 chiffres commençant par 0 est considéré comme congolais (+243).
 */
final class Telephone
{
    public static function normaliser(string $saisie): ?string
    {
        $chiffres = preg_replace('/[\s.\-()]/', '', trim($saisie)) ?? '';

        $international = match (true) {
            str_starts_with($chiffres, '+') => $chiffres,
            str_starts_with($chiffres, '00') => '+'.substr($chiffres, 2),
            preg_match('/^0\d{9}$/', $chiffres) === 1 => '+243'.substr($chiffres, 1),
            preg_match('/^243\d{9}$/', $chiffres) === 1 => '+'.$chiffres,
            default => null,
        };

        return $international !== null && preg_match('/^\+\d{8,15}$/', $international) === 1 ? $international : null;
    }
}
