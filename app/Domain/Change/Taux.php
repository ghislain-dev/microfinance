<?php

declare(strict_types=1);

namespace App\Domain\Change;

use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Taux de change en CDF pour 1 USD, stocké en millionièmes (entier) : 2 850,5 → 2 850 500 000.
 * Conversions en entiers (brick/math), arrondies au centime inférieur (décision du 2026-10-31).
 */
final readonly class Taux
{
    private const ECHELLE = 1_000_000;

    public function __construct(public int $millioniemes)
    {
        if ($millioniemes <= 0) {
            throw new InvalidArgumentException('Le taux doit être positif.');
        }
    }

    public static function depuisSaisie(string $saisie): self
    {
        $texte = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim($saisie));
        if (preg_match('/^\d{1,9}(\.\d{1,6})?$/', $texte) !== 1) {
            throw new InvalidArgumentException("Taux invalide : « {$saisie} » (6 décimales au plus).");
        }
        [$unites, $decimales] = array_pad(explode('.', $texte), 2, '0');

        return new self((int) $unites * self::ECHELLE + (int) str_pad($decimales, 6, '0'));
    }

    /**
     * Taux moyen (achat + vente) / 2, en fraction exacte : sert à mesurer la marge.
     */
    public static function moyenEntre(self $achat, self $vente): self
    {
        return new self(intdiv($achat->millioniemes + $vente->millioniemes, 2));
    }

    public function versCdf(Montant $usd): Montant
    {
        return new Montant(BigInteger::of($usd->centimes)->multipliedBy($this->millioniemes)
            ->dividedBy(self::ECHELLE, RoundingMode::Down)->toInt(), Devise::CDF);
    }

    public function versUsd(Montant $cdf): Montant
    {
        return new Montant(BigInteger::of($cdf->centimes)->multipliedBy(self::ECHELLE)
            ->dividedBy($this->millioniemes, RoundingMode::Down)->toInt(), Devise::USD);
    }

    public function comparer(self $autre): int
    {
        return $this->millioniemes <=> $autre->millioniemes;
    }

    public function formate(): string
    {
        $unites = intdiv($this->millioniemes, self::ECHELLE);

        return strrev(implode(' ', str_split(strrev((string) $unites), 3))).','
            .str_pad((string) ($this->millioniemes % self::ECHELLE), 6, '0', STR_PAD_LEFT);
    }
}
