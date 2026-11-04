<?php

declare(strict_types=1);

namespace App\Domain\Credit\Services;

use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\StatutPret;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Temps\Horloge;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs du portefeuille (skill §6) : encours = capital restant dû des prêts en cours ;
 * PAR N = encours des prêts ayant une échéance impayée depuis plus de N jours ÷ encours total.
 */
final class IndicateursCredit
{
    public function par(int $jours, Devise $devise, ?CarbonImmutable $au = null): Par
    {
        $au ??= Horloge::aujourdhui();
        $enCours = fn () => EcheancePret::query()->where('devise', $devise->value)
            ->whereHas('pret', fn ($q) => $q->where('statut', StatutPret::EnCours->value));

        $encours = (int) $enCours()->sum(DB::raw('capital - capital_paye'));
        $pretsEnRetard = $enCours()->whereNull('payee_le')
            ->where('date_echeance', '<', $au->subDays($jours)->toDateString())->distinct()->pluck('pret_id');
        $aRisque = (int) $enCours()->whereIn('pret_id', $pretsEnRetard)->sum(DB::raw('capital - capital_paye'));

        $taux = $encours === 0 ? 0 : BigInteger::of($aRisque)->multipliedBy(10_000)->dividedBy($encours, RoundingMode::HalfEven)->toInt();

        return new Par($jours, new Montant($aRisque, $devise), new Montant($encours, $devise), $taux);
    }
}
