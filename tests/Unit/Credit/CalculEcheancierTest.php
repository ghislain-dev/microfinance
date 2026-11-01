<?php

declare(strict_types=1);

use App\Domain\Credit\Echeancier\CalculEcheancier;
use App\Domain\Credit\Echeancier\EcheancePrevue;
use App\Domain\Credit\MethodeCalcul;
use App\Domain\Credit\Periodicite;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Carbon\CarbonImmutable;

/**
 * @return list<array{0: string, 1: int, 2: int}> [date, capital, intérêt]
 */
function lignesEcheancier(int $capital, int $tauxPb, int $nombre, Periodicite $periodicite, MethodeCalcul $methode, string $debut = '2026-01-15'): array
{
    return array_map(fn (EcheancePrevue $e) => [$e->date->toDateString(), $e->capital->centimes, $e->interet->centimes],
        CalculEcheancier::calculer(new Montant($capital, Devise::USD), $tauxPb, $nombre, $periodicite, $methode, CarbonImmutable::parse($debut)));
}

it('linéaire : capital constant, écart d’arrondi sur la dernière échéance, intérêt sur le capital restant', function () {
    expect(lignesEcheancier(100_000, 300, 3, Periodicite::Mensuelle, MethodeCalcul::Lineaire))->toBe([
        ['2026-02-15', 33_333, 3_000],
        ['2026-03-15', 33_333, 2_000], // 666,67 × 3 % = 20,0001
        ['2026-04-15', 33_334, 1_000], // 333,34 × 3 % = 10,0002
    ]);
});

it('dégressif : annuité constante arrondie au centime, dernière échéance solde le capital', function () {
    // 1 000 × 0,03 / (1 − 1,03⁻³) = 353,5304 → 353,53
    expect(lignesEcheancier(100_000, 300, 3, Periodicite::Mensuelle, MethodeCalcul::Degressif))->toBe([
        ['2026-02-15', 32_353, 3_000],
        ['2026-03-15', 33_324, 2_029], // 676,47 × 3 % = 20,2941
        ['2026-04-15', 34_323, 1_030], // 343,23 × 3 % = 10,2969
    ]);
});

it('arrondit chaque intérêt au centime en HALF_EVEN', function () {
    // 2,50 USD × 1 % = 2,5 centimes → 2 ; 1,50 USD × 1 % = 1,5 centime → 2
    expect(lignesEcheancier(250, 100, 1, Periodicite::Mensuelle, MethodeCalcul::Lineaire)[0][2])->toBe(2)
        ->and(lignesEcheancier(150, 100, 1, Periodicite::Mensuelle, MethodeCalcul::Lineaire)[0][2])->toBe(2);
});

it('convertit le taux mensuel : × 12 / 52 chaque semaine, × 12 / 26 toutes les deux semaines', function () {
    // 1 000 × 3 % × 12 / 52 = 6,923… → 6,92 ; × 12 / 26 = 13,846… → 13,85
    expect(lignesEcheancier(100_000, 300, 4, Periodicite::Hebdomadaire, MethodeCalcul::Lineaire)[0])->toBe(['2026-01-22', 25_000, 692])
        ->and(lignesEcheancier(100_000, 300, 4, Periodicite::Bimensuelle, MethodeCalcul::Lineaire)[0])->toBe(['2026-01-29', 25_000, 1_385]);
});

it('garde la fin de mois sans déborder', function () {
    expect(array_column(lignesEcheancier(30_000, 200, 3, Periodicite::Mensuelle, MethodeCalcul::Lineaire, '2026-01-31'), 0))
        ->toBe(['2026-02-28', '2026-03-31', '2026-04-30']);
});

it('taux nul : aucun intérêt, capital réparti', function (MethodeCalcul $methode) {
    expect(lignesEcheancier(10_000, 0, 3, Periodicite::Mensuelle, $methode))->toBe([
        ['2026-02-15', 3_333, 0], ['2026-03-15', 3_333, 0], ['2026-04-15', 3_334, 0],
    ]);
})->with([MethodeCalcul::Lineaire, MethodeCalcul::Degressif]);

it('Σ capital des échéances = montant décaissé, au centime près', function (MethodeCalcul $methode, Periodicite $periodicite) {
    foreach ([[1, 1, 1], [99_999, 250, 7], [1_234_567, 375, 24], [5_000_000_00, 500, 104], [10_001, 1, 12], [77, 1_000, 52]] as [$capital, $taux, $nombre]) {
        $lignes = lignesEcheancier($capital, $taux, $nombre, $periodicite, $methode);
        expect($lignes)->toHaveCount($nombre)
            ->and(array_sum(array_column($lignes, 1)))->toBe($capital);
        foreach ($lignes as [, $capitalEcheance, $interet]) {
            expect($capitalEcheance)->toBeGreaterThanOrEqual(0)->and($interet)->toBeGreaterThanOrEqual(0);
        }
    }
})->with([MethodeCalcul::Lineaire, MethodeCalcul::Degressif])
    ->with([Periodicite::Hebdomadaire, Periodicite::Bimensuelle, Periodicite::Mensuelle]);

it('dégressif : toutes les échéances valent l’annuité, sauf la dernière', function () {
    $lignes = lignesEcheancier(1_234_567, 375, 24, Periodicite::Mensuelle, MethodeCalcul::Degressif);
    $totaux = array_map(fn (array $l) => $l[1] + $l[2], $lignes);
    expect(array_unique(array_slice($totaux, 0, -1)))->toHaveCount(1)
        ->and(abs(end($totaux) - $totaux[0]))->toBeLessThanOrEqual(24);
});

it('refuse un capital nul, un taux négatif ou aucune échéance', function (int $capital, int $taux, int $nombre) {
    lignesEcheancier($capital, $taux, $nombre, Periodicite::Mensuelle, MethodeCalcul::Lineaire);
})->throws(InvalidArgumentException::class)->with([[0, 300, 3], [-100, 300, 3], [10_000, -1, 3], [10_000, 300, 0]]);
