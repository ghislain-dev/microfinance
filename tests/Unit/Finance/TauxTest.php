<?php

declare(strict_types=1);

use App\Domain\Change\Taux;

it('lit un taux saisi en millionièmes, sans float', function (string $saisie, int $millioniemes) {
    expect(Taux::depuisSaisie($saisie)->millioniemes)->toBe($millioniemes);
})->with([
    ['2850', 2_850_000_000],
    ['2850,5', 2_850_500_000],
    ['2 850,123456', 2_850_123_456],
    ['0,000001', 1],
]);

it('refuse un taux nul, négatif ou trop précis', function (string $saisie) {
    Taux::depuisSaisie($saisie);
})->throws(InvalidArgumentException::class)->with(['0', '-1', '2850,1234567', 'abc', '']);

it('affiche le taux', function () {
    expect(Taux::depuisSaisie('2850,5')->formate())->toBe('2 850,500000');
});
