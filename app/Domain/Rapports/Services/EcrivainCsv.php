<?php

declare(strict_types=1);

namespace App\Domain\Rapports\Services;

/**
 * CSV pour un tableur en français : séparateur point-virgule, UTF-8 avec BOM, guillemets seulement si nécessaire.
 */
final class EcrivainCsv
{
    public function ecrire(Tableau $tableau): string
    {
        $lignes = [[$tableau->titre], [$tableau->sousTitre], $tableau->colonnes, ...$tableau->lignes];

        return "\u{FEFF}".implode("\r\n", array_map(fn (array $l) => implode(';', array_map($this->champ(...), $l)), $lignes))."\r\n";
    }

    private function champ(string $valeur): string
    {
        // Une formule (=, +, -, @ en tête) serait exécutée par le tableur : on la neutralise.
        if ($valeur !== '' && in_array($valeur[0], ['=', '+', '@'], true)) {
            $valeur = "'".$valeur;
        }

        return preg_match('/[;"\r\n]/', $valeur) === 1 ? '"'.str_replace('"', '""', $valeur).'"' : $valeur;
    }
}
