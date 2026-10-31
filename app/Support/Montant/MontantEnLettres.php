<?php

declare(strict_types=1);

namespace App\Support\Montant;

/**
 * Montant écrit en toutes lettres pour les reçus (orthographe traditionnelle :
 * « vingt et un », « quatre-vingts », « deux cents », mais « quatre-vingt mille »).
 */
final class MontantEnLettres
{
    private const UNITES = [
        '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix',
        'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private const DIZAINES = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    /** devise => [unité singulier, unité pluriel, sous-unité singulier, sous-unité pluriel] */
    private const LIBELLES = [
        'USD' => ['dollar américain', 'dollars américains', 'cent', 'cents'],
        'CDF' => ['franc congolais', 'francs congolais', 'centime', 'centimes'],
    ];

    public static function ecrire(Montant $montant): string
    {
        $centimes = $montant->estNegatif() ? $montant->negation()->centimes : $montant->centimes;
        $unites = intdiv($centimes, 100);
        $sous = $centimes % 100;
        [$unite, $unitesPluriel, $sousUnite, $sousUnitesPluriel] = self::LIBELLES[$montant->devise->value];

        $texte = self::nombre($unites).' '.($unites > 1 ? $unitesPluriel : $unite);
        if ($sous > 0) {
            $texte .= ' et '.self::nombre($sous).' '.($sous > 1 ? $sousUnitesPluriel : $sousUnite);
        }

        return ($montant->estNegatif() ? 'moins ' : '').$texte;
    }

    public static function nombre(int $nombre): string
    {
        if ($nombre === 0) {
            return 'zéro';
        }

        $morceaux = [];
        foreach ([1_000_000_000 => 'milliard', 1_000_000 => 'million'] as $valeur => $nom) {
            $quantite = intdiv($nombre, $valeur);
            if ($quantite > 0) {
                $morceaux[] = self::nombre($quantite).' '.$nom.($quantite > 1 ? 's' : '');
                $nombre %= $valeur;
            }
        }

        $milliers = intdiv($nombre, 1000);
        if ($milliers > 0) {
            // « mille » est invariable et « cent » / « vingt » ne prennent pas de s devant lui.
            $morceaux[] = $milliers === 1 ? 'mille' : self::moinsDeMille($milliers, false).' mille';
            $nombre %= 1000;
        }

        if ($nombre > 0) {
            $morceaux[] = self::moinsDeMille($nombre, true);
        }

        return implode(' ', $morceaux);
    }

    private static function moinsDeMille(int $nombre, bool $final): string
    {
        $centaines = intdiv($nombre, 100);
        $reste = $nombre % 100;
        if ($centaines === 0) {
            return self::moinsDeCent($reste, $final);
        }

        $cent = $centaines === 1 ? 'cent' : self::UNITES[$centaines].' cent'.($reste === 0 && $final ? 's' : '');

        return $reste === 0 ? $cent : $cent.' '.self::moinsDeCent($reste, $final);
    }

    private static function moinsDeCent(int $nombre, bool $final): string
    {
        if ($nombre < 20) {
            return self::UNITES[$nombre];
        }
        if ($nombre < 70) {
            $dizaine = self::DIZAINES[intdiv($nombre, 10)];
            $unite = $nombre % 10;

            return match ($unite) {
                0 => $dizaine,
                1 => $dizaine.' et un',
                default => $dizaine.'-'.self::UNITES[$unite],
            };
        }
        if ($nombre < 80) {
            return $nombre === 71 ? 'soixante et onze' : 'soixante-'.self::UNITES[$nombre - 60];
        }
        if ($nombre === 80) {
            return $final ? 'quatre-vingts' : 'quatre-vingt';
        }

        return 'quatre-vingt-'.self::UNITES[$nombre - 80];
    }
}
