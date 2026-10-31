<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\DeuxOrganisations;
use Tests\Support\GrandLivre;

it('bloque UPDATE/DELETE et les écritures invalides dans MySQL', function () {
    $o = DeuxOrganisations::creer();
    $compte = GrandLivre::compte($o->userA);
    $operation = GrandLivre::operationBrute($o->userA);
    GrandLivre::ecritureBrute($operation, $compte, 500, 0);

    expect(fn () => DB::statement('update ecritures set debit = 1'))->toThrow(QueryException::class, 'ecritures : insertion seule')
        ->and(fn () => DB::statement('delete from operations'))->toThrow(QueryException::class, 'operations : insertion seule')
        ->and(fn () => GrandLivre::ecritureBrute($operation, $compte, 100, 100))->toThrow(QueryException::class, 'un débit ou un crédit');
})->group('mysql');
