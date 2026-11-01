<?php

declare(strict_types=1);

namespace App\Support\Montant;

use InvalidArgumentException;

/**
 * Pourcentages saisis en texte, stockés en points de base entiers (jamais de float).
 */
final class Pourcentage
{
    /**
     * « 3,25 » (%) → 325 points de base ; deux décimales au plus ; vide → 0.
     */
    public static function pointsDeBase(string $saisie, string $libelle = 'Taux'): int
    {
        $texte = str_replace([' ', ','], ['', '.'], trim($saisie));
        if ($texte === '') {
            return 0;
        }
        if (preg_match('/^\d{1,3}(\.\d{1,2})?$/', $texte) !== 1) {
            throw new InvalidArgumentException("{$libelle} invalide : « {$saisie} » (ex. 3,25).");
        }
        [$unites, $decimales] = array_pad(explode('.', $texte), 2, '0');

        return (int) $unites * 100 + (int) str_pad($decimales, 2, '0');
    }

    /**
     * 325 → « 3,25 % ».
     */
    public static function formate(int $pointsDeBase): string
    {
        return intdiv($pointsDeBase, 100).','.str_pad((string) ($pointsDeBase % 100), 2, '0', STR_PAD_LEFT).' %';
    }
}
