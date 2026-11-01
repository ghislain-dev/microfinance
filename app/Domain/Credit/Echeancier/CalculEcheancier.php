<?php

declare(strict_types=1);

namespace App\Domain\Credit\Echeancier;

use App\Domain\Credit\MethodeCalcul;
use App\Domain\Credit\Periodicite;
use App\Support\Montant\Montant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Échéancier d'un prêt (skill §6) : intérêt = capital restant × taux de la période, arrondi au centime
 * en HALF_EVEN ; écart d'arrondi du capital sur la dernière échéance, donc Σ capital = montant décaissé.
 */
final class CalculEcheancier
{
    private const ECHELLE = 10;

    /**
     * @param  int  $tauxMensuelPb  taux mensuel en points de base (300 = 3 % par mois)
     * @return list<EcheancePrevue>
     */
    public static function calculer(Montant $capital, int $tauxMensuelPb, int $nombre, Periodicite $periodicite, MethodeCalcul $methode, CarbonImmutable $decaissement): array
    {
        if (! $capital->estPositif() || $tauxMensuelPb < 0 || $nombre < 1) {
            throw new InvalidArgumentException('Échéancier : capital positif, taux positif ou nul et au moins une échéance.');
        }

        [$numerateur, $denominateur] = $periodicite->facteurTauxMensuel();
        $taux = BigDecimal::of($tauxMensuelPb)->multipliedBy($numerateur)
            ->dividedBy(10_000 * $denominateur, self::ECHELLE, RoundingMode::HalfEven);
        $annuite = $methode === MethodeCalcul::Degressif ? self::annuite($capital->centimes, $taux, $nombre) : 0;

        $echeances = [];
        $restant = $capital->centimes;
        for ($rang = 1; $rang <= $nombre; $rang++) {
            $interet = BigDecimal::of($restant)->multipliedBy($taux)->toScale(0, RoundingMode::HalfEven)->toInt();
            $part = match (true) {
                $rang === $nombre => $restant,
                $methode === MethodeCalcul::Lineaire => intdiv($capital->centimes, $nombre),
                default => max(0, min($restant, $annuite - $interet)),
            };
            $restant -= $part;
            $echeances[] = new EcheancePrevue($rang, $periodicite->dateEcheance($decaissement, $rang),
                new Montant($part, $capital->devise), new Montant($interet, $capital->devise));
        }

        return $echeances;
    }

    /**
     * Annuité constante C × t / (1 − (1 + t)⁻ⁿ), écrite C × t × q / (q − 1) avec q = (1 + t)ⁿ, au centime (HALF_EVEN).
     */
    private static function annuite(int $capital, BigDecimal $taux, int $nombre): int
    {
        if ($taux->isZero()) {
            return intdiv($capital, $nombre);
        }
        $q = $taux->plus(1)->power($nombre);

        return BigDecimal::of($capital)->multipliedBy($taux)->multipliedBy($q)
            ->dividedBy($q->minus(1), self::ECHELLE, RoundingMode::HalfEven)
            ->toScale(0, RoundingMode::HalfEven)->toInt();
    }
}
