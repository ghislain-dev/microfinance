<?php

declare(strict_types=1);

namespace App\Domain\Caisse;

use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use InvalidArgumentException;

/**
 * Comptage physique d'une caisse : nombre de billets par coupure, en entiers.
 * Billets seulement (décision du 2026-10-31).
 */
final readonly class Billetage
{
    public const COUPURES = [
        'USD' => [1, 2, 5, 10, 20, 50, 100],
        'CDF' => [50, 100, 200, 500, 1000, 5000, 10000, 20000],
    ];

    /**
     * @param  array<string, array<int, int>>  $nombres  devise => [coupure => nombre]
     */
    private function __construct(private array $nombres) {}

    public static function vide(): self
    {
        return new self([]);
    }

    /**
     * @param  array<mixed>  $saisie  devise => [coupure => nombre], tel que reçu du formulaire
     */
    public static function depuisSaisie(array $saisie): self
    {
        $nombres = [];
        foreach ($saisie as $code => $lignes) {
            $devise = Devise::tryFrom((string) $code) ?? throw new InvalidArgumentException("Devise inconnue : {$code}");
            foreach ((array) $lignes as $coupure => $nombre) {
                if (! in_array((int) $coupure, self::COUPURES[$devise->value], true) || (string) (int) $coupure !== (string) $coupure) {
                    throw new InvalidArgumentException("Coupure {$coupure} {$devise->value} non autorisée.");
                }
                if (filter_var($nombre, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10_000_000]]) === false) {
                    throw new InvalidArgumentException("Nombre de billets invalide pour {$coupure} {$devise->value}.");
                }
                if ((int) $nombre > 0) {
                    $nombres[$devise->value][(int) $coupure] = (int) $nombre;
                }
            }
        }

        return new self($nombres);
    }

    public function total(Devise $devise): Montant
    {
        $total = Montant::zero($devise);
        foreach ($this->nombres[$devise->value] ?? [] as $coupure => $nombre) {
            $total = $total->plus(new Montant($coupure * 100 * $nombre, $devise));
        }

        return $total;
    }

    /**
     * @return list<array{devise: Devise, coupure: int, nombre: int}>
     */
    public function lignes(): array
    {
        $lignes = [];
        foreach ($this->nombres as $devise => $parCoupure) {
            foreach ($parCoupure as $coupure => $nombre) {
                $lignes[] = ['devise' => Devise::from($devise), 'coupure' => $coupure, 'nombre' => $nombre];
            }
        }

        return $lignes;
    }
}
