<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Remuneration\Actions;

use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Remuneration\Lots;
use App\Domain\Epargne\Remuneration\Models\Remuneration;
use App\Domain\Organisation\Models\Organisation;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Temps\Horloge;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Intérêts d'un mois écoulé (2026-10-31) : solde de fin de mois × taux annuel / 12, arrondi au centime
 * inférieur. Produit un lot à valider ; rien n'est versé ici.
 */
final class CalculerInterets
{
    public function __construct(private readonly Lots $lots) {}

    public function executer(Organisation $organisation, string $periode, Devise $devise): Remuneration
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode) !== 1) {
            throw new InvalidArgumentException("Période invalide : « {$periode} » (AAAA-MM).");
        }
        $fin = CarbonImmutable::parse("{$periode}-01")->endOfMonth()->startOfDay();
        if (! $fin->isBefore(Horloge::aujourdhui($organisation->id))) {
            throw new InvalidArgumentException("Le mois {$periode} n'est pas terminé.");
        }

        $lignes = array_map(fn (array $l) => [...$l, 'montant' => new Montant(
            // base × points de base / (10 000 × 12), au centime inférieur
            BigInteger::of($l['base']->centimes)->multipliedBy($l['compte']->type->taux_interet_pb)
                ->dividedBy(120_000, RoundingMode::Down)->toInt(),
            $devise,
        )], $this->lots->eligibles($organisation, $devise, $fin, fn (CompteEpargne $c) => $c->type->taux_interet_pb > 0));

        return $this->lots->creer($organisation, 'interets', $devise, $periode, $fin, $lignes);
    }
}
