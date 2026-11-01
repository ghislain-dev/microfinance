<?php

declare(strict_types=1);

namespace App\Domain\Credit;

use Carbon\CarbonImmutable;

/**
 * Périodicité des échéances, fixée par le produit de crédit (décision du 2026-11-01).
 */
enum Periodicite: string
{
    case Hebdomadaire = 'hebdomadaire';
    case Bimensuelle = 'bimensuelle'; // toutes les deux semaines
    case Mensuelle = 'mensuelle';

    public function libelle(): string
    {
        return match ($this) {
            self::Hebdomadaire => 'Chaque semaine',
            self::Bimensuelle => 'Toutes les deux semaines',
            self::Mensuelle => 'Chaque mois',
        };
    }

    /**
     * Date de l'échéance n° $rang : $rang périodes après le décaissement, sans débordement de fin de mois.
     */
    public function dateEcheance(CarbonImmutable $decaissement, int $rang): CarbonImmutable
    {
        return match ($this) {
            self::Hebdomadaire => $decaissement->addWeeks($rang),
            self::Bimensuelle => $decaissement->addWeeks(2 * $rang),
            self::Mensuelle => $decaissement->addMonthsNoOverflow($rang),
        };
    }

    /**
     * Taux de la période = taux mensuel × numérateur / dénominateur (× 12 / 52 chaque semaine).
     *
     * @return array{0: int, 1: int}
     */
    public function facteurTauxMensuel(): array
    {
        return match ($this) {
            self::Hebdomadaire => [12, 52],
            self::Bimensuelle => [12, 26],
            self::Mensuelle => [1, 1],
        };
    }
}
