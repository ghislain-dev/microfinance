<?php

declare(strict_types=1);

use App\Domain\Finance\Models\Ecriture;
use App\Domain\Finance\Models\Operation;
use App\Support\BaseDeDonnees\ModificationInterdite;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\DeuxOrganisations;
use Tests\Support\GrandLivre;

it('refuse update et delete sur une opération ou une écriture (modèle)', function () {
    $o = DeuxOrganisations::creer();
    $compte = GrandLivre::compte($o->userA);
    $id = GrandLivre::operationBrute($o->userA);
    GrandLivre::ecritureBrute($id, $compte, 500, 0);
    $operation = Operation::findOrFail($id);
    $ecriture = Ecriture::firstOrFail();

    expect(fn () => $operation->update(['motif' => 'retouche']))->toThrow(ModificationInterdite::class)
        ->and(fn () => $operation->delete())->toThrow(ModificationInterdite::class)
        ->and(fn () => $ecriture->update(['debit' => 1]))->toThrow(ModificationInterdite::class)
        ->and(fn () => $ecriture->delete())->toThrow(ModificationInterdite::class);
});

it('refuse UPDATE et DELETE en SQL direct (triggers)', function (string $sql) {
    $o = DeuxOrganisations::creer();
    $compte = GrandLivre::compte($o->userA);
    GrandLivre::ecritureBrute(GrandLivre::operationBrute($o->userA), $compte, 500, 0);

    expect(fn () => DB::statement($sql))->toThrow(QueryException::class, 'insertion seule');
})->with([
    'operations UPDATE' => "update operations set motif = 'x'",
    'operations DELETE' => 'delete from operations',
    'ecritures UPDATE' => 'update ecritures set debit = 1',
    'ecritures DELETE' => 'delete from ecritures',
]);

it('exige exactement un débit ou un crédit strictement positif par écriture', function (int $debit, int $credit) {
    $o = DeuxOrganisations::creer();
    $compte = GrandLivre::compte($o->userA);

    GrandLivre::ecritureBrute(GrandLivre::operationBrute($o->userA), $compte, $debit, $credit);
})->throws(QueryException::class, 'un débit ou un crédit')->with([
    'débit et crédit' => [100, 100],
    'zéro et zéro' => [0, 0],
]);

it('refuse un montant négatif en base (UNSIGNED sur MySQL, trigger sur SQLite)', function () {
    $o = DeuxOrganisations::creer();
    $compte = GrandLivre::compte($o->userA);

    try {
        GrandLivre::ecritureBrute(GrandLivre::operationBrute($o->userA), $compte, -100, 0);
        throw new RuntimeException('Débit négatif accepté');
    } catch (QueryException $e) {
        expect($e->getMessage())->toMatch('/un débit ou un crédit|Out of range/');
    }
});

it('refuse une clé d’idempotence en double', function () {
    $o = DeuxOrganisations::creer();

    GrandLivre::operationBrute($o->userA, 'cle-unique');
    GrandLivre::operationBrute($o->userB, 'cle-unique');
})->throws(UniqueConstraintViolationException::class);

it('refuse une écriture sur le compte d’une autre organisation', function () {
    $o = DeuxOrganisations::creer();
    $compteB = GrandLivre::compte($o->userB);
    $operationA = GrandLivre::operationBrute($o->userA);

    try {
        DB::table('ecritures')->insert([
            'organisation_id' => $o->orgA->id, 'operation_id' => $operationA, 'compte_id' => $compteB->id,
            'debit' => 100, 'credit' => 0, 'devise' => 'USD', 'created_at' => now(),
        ]);
        throw new RuntimeException('Écriture acceptée sur le compte d’une autre organisation');
    } catch (QueryException $e) {
        expect(mb_strtolower($e->getMessage()))->toContain('foreign key');
    }
});

it('n’a ni updated_at ni SoftDeletes', function () {
    expect(Schema::hasColumn('operations', 'updated_at'))->toBeFalse()
        ->and(Schema::hasColumn('ecritures', 'updated_at'))->toBeFalse()
        ->and(Schema::hasColumn('operations', 'deleted_at'))->toBeFalse()
        ->and(class_uses_recursive(Operation::class))->not->toContain(SoftDeletes::class);
});
