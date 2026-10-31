<?php

declare(strict_types=1);

use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantInvalide;

it('convertit une saisie en centimes, avec point ou virgule', function (string $saisie, int $centimes) {
    expect(Montant::depuisSaisie($saisie, Devise::USD)->centimes)->toBe($centimes);
})->with([
    ['12.50', 1250],
    ['12,50', 1250],
    ['12,5', 1250],
    ['12', 1200],
    ['0,01', 1],
    [' 1 250,75 ', 125075],
    ['9999999999999.99', 999999999999999],
]);

it('refuse une saisie invalide', function (string $saisie) {
    Montant::depuisSaisie($saisie, Devise::CDF);
})->throws(MontantInvalide::class)->with([
    'trois décimales' => '12,505',
    'négatif' => '-5',
    'vide' => '',
    'lettres' => '12a',
    'séparateur de milliers à point' => '1.250,50',
    'notation scientifique' => '1e5',
    'trop grand' => '10000000000000',
]);

it('additionne et soustrait en entiers', function () {
    $a = Montant::depuisSaisie('10,10', Devise::USD);
    $b = Montant::depuisSaisie('0,20', Devise::USD);

    expect($a->plus($b)->centimes)->toBe(1030)
        ->and($b->moins($a)->centimes)->toBe(-990)
        ->and($b->moins($a)->estNegatif())->toBeTrue()
        ->and($a->moins($a)->estZero())->toBeTrue()
        ->and($a->negation()->centimes)->toBe(-1010)
        ->and($a->estPositif())->toBeTrue();
});

it('donne un résultat exact là où un float se tromperait (0,1 + 0,2)', function () {
    $somme = Montant::depuisSaisie('0,1', Devise::USD)->plus(Montant::depuisSaisie('0,2', Devise::USD));

    expect($somme->egal(Montant::depuisSaisie('0,3', Devise::USD)))->toBeTrue();
});

it('refuse de mélanger deux devises (une opération = une devise)', function () {
    Montant::depuisSaisie('1', Devise::USD)->plus(Montant::depuisSaisie('1', Devise::CDF));
})->throws(LogicException::class);

it('refuse un dépassement d’entier au lieu de passer en float', function () {
    (new Montant(PHP_INT_MAX, Devise::USD))->plus(new Montant(1, Devise::USD));
})->throws(OverflowException::class);

it('formate pour l’affichage sans passer par un float', function (int $centimes, string $attendu) {
    expect((new Montant($centimes, Devise::CDF))->formate())->toBe($attendu);
})->with([
    [125075, '1 250,75 CDF'],
    [5, '0,05 CDF'],
    [-123456789, '-1 234 567,89 CDF'],
    [0, '0,00 CDF'],
]);

it('compare deux montants de même devise', function () {
    $petit = new Montant(100, Devise::USD);
    $grand = new Montant(200, Devise::USD);

    expect($petit->comparer($grand))->toBe(-1)
        ->and($grand->comparer($petit))->toBe(1)
        ->and($petit->comparer(new Montant(100, Devise::USD)))->toBe(0)
        ->and(Montant::zero(Devise::CDF)->estZero())->toBeTrue();
});
