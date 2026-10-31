<?php

declare(strict_types=1);

namespace App\Support\Montant;

use LogicException;
use OverflowException;

/**
 * Montant d'argent en entier d'unité mineure (centimes) + devise. Aucun float, jamais (CLAUDE.md §4.1).
 */
final readonly class Montant
{
    public function __construct(public int $centimes, public Devise $devise) {}

    public static function zero(Devise $devise): self
    {
        return new self(0, $devise);
    }

    /**
     * « 12.50 », « 12,50 » ou « 1 250,50 » → 1250 / 125050 centimes. Refuse le négatif et plus de 2 décimales.
     */
    public static function depuisSaisie(string $saisie, Devise $devise): self
    {
        $texte = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim($saisie));
        if (preg_match('/^\d{1,13}(\.\d{1,2})?$/', $texte) !== 1) {
            throw new MontantInvalide("Montant invalide : « {$saisie} »");
        }

        [$unites, $decimales] = array_pad(explode('.', $texte), 2, '0');

        return new self((int) $unites * 100 + (int) str_pad($decimales, 2, '0'), $devise);
    }

    public function plus(self $autre): self
    {
        $this->memeDevise($autre);

        return new self(self::entier($this->centimes + $autre->centimes), $this->devise);
    }

    public function moins(self $autre): self
    {
        $this->memeDevise($autre);

        return new self(self::entier($this->centimes - $autre->centimes), $this->devise);
    }

    public function negation(): self
    {
        return new self(self::entier(-$this->centimes), $this->devise);
    }

    public function comparer(self $autre): int
    {
        $this->memeDevise($autre);

        return $this->centimes <=> $autre->centimes;
    }

    public function egal(self $autre): bool
    {
        return $this->devise === $autre->devise && $this->centimes === $autre->centimes;
    }

    public function estPositif(): bool
    {
        return $this->centimes > 0;
    }

    public function estNegatif(): bool
    {
        return $this->centimes < 0;
    }

    public function estZero(): bool
    {
        return $this->centimes === 0;
    }

    /**
     * Affichage seulement : « 1 250,75 CDF ». Calculé en entiers.
     */
    public function formate(): string
    {
        $chiffres = ltrim((string) $this->centimes, '-');
        $chiffres = str_pad($chiffres, 3, '0', STR_PAD_LEFT);
        $unites = substr($chiffres, 0, -2);
        $milliers = strrev(implode(' ', str_split(strrev($unites), 3)));

        return ($this->estNegatif() ? '-' : '').$milliers.','.substr($chiffres, -2).' '.$this->devise->value;
    }

    private function memeDevise(self $autre): void
    {
        if ($autre->devise !== $this->devise) {
            throw new LogicException("Devises différentes : {$this->devise->value} et {$autre->devise->value}");
        }
    }

    /**
     * En PHP, un dépassement d'entier donne silencieusement un float : on l'interdit.
     */
    private static function entier(mixed $valeur): int
    {
        if (! is_int($valeur)) {
            throw new OverflowException('Dépassement de capacité sur un montant');
        }

        return $valeur;
    }
}
