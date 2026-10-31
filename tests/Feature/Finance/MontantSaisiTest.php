<?php

declare(strict_types=1);

use App\Support\Montant\MontantSaisi;
use Illuminate\Support\Facades\Validator;

it('valide un montant saisi', function (mixed $valeur, bool $attendu) {
    expect(Validator::make(['montant' => $valeur], ['montant' => ['required', new MontantSaisi]])->passes())->toBe($attendu);
})->with([
    ['1250,50', true],
    ['0.01', true],
    ['0', false],
    ['-10', false],
    ['10,555', false],
    [12.5, false], // un nombre JSON passe par un float : refusé, le mobile envoie une chaîne
    ['abc', false],
]);
