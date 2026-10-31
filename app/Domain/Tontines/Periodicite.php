<?php

declare(strict_types=1);

namespace App\Domain\Tontines;

use Carbon\CarbonImmutable;

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
     * Date de la séance n° $rang (1 = date de début).
     */
    public function dateSeance(CarbonImmutable $debut, int $rang): CarbonImmutable
    {
        return match ($this) {
            self::Hebdomadaire => $debut->addWeeks($rang - 1),
            self::Bimensuelle => $debut->addWeeks(2 * ($rang - 1)),
            self::Mensuelle => $debut->addMonthsNoOverflow($rang - 1),
        };
    }
}
