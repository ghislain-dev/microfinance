<?php

declare(strict_types=1);

use App\Support\Telephone;

it('normalise un numéro au format international RDC', function (string $saisie, string $attendu) {
    expect(Telephone::normaliser($saisie))->toBe($attendu);
})->with([
    ['0812345678', '+243812345678'],
    ['081 234 56 78', '+243812345678'],
    ['+243 81-234-5678', '+243812345678'],
    ['00243812345678', '+243812345678'],
    ['243812345678', '+243812345678'],
    ['+33612345678', '+33612345678'],
]);

it('refuse un numéro invalide', function (string $saisie) {
    expect(Telephone::normaliser($saisie))->toBeNull();
})->with(['', 'abc', '12', '0812']);
