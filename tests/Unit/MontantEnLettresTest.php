<?php

declare(strict_types=1);

use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantEnLettres;

it('écrit les nombres en lettres (orthographe traditionnelle)', function (int $nombre, string $attendu) {
    expect(MontantEnLettres::nombre($nombre))->toBe($attendu);
})->with([
    [0, 'zéro'], [1, 'un'], [16, 'seize'], [17, 'dix-sept'], [21, 'vingt et un'], [22, 'vingt-deux'],
    [70, 'soixante-dix'], [71, 'soixante et onze'], [77, 'soixante-dix-sept'], [80, 'quatre-vingts'],
    [81, 'quatre-vingt-un'], [91, 'quatre-vingt-onze'], [99, 'quatre-vingt-dix-neuf'], [100, 'cent'],
    [101, 'cent un'], [200, 'deux cents'], [201, 'deux cent un'], [1000, 'mille'], [1001, 'mille un'],
    [2000, 'deux mille'], [80000, 'quatre-vingt mille'], [200000, 'deux cent mille'], [1000000, 'un million'],
    [2500000, 'deux millions cinq cent mille'], [1000000000, 'un milliard'],
]);

it('écrit un montant avec sa devise', function (string $saisie, Devise $devise, string $attendu) {
    expect(MontantEnLettres::ecrire(Montant::depuisSaisie($saisie, $devise)))->toBe($attendu);
})->with([
    ['125,50', Devise::USD, 'cent vingt-cinq dollars américains et cinquante cents'],
    ['1', Devise::USD, 'un dollar américain'],
    ['0,01', Devise::USD, 'zéro dollar américain et un cent'],
    ['20000', Devise::CDF, 'vingt mille francs congolais'],
    ['1,05', Devise::CDF, 'un franc congolais et cinq centimes'],
]);
