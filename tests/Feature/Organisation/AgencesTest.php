<?php

declare(strict_types=1);

use App\Domain\Organisation\Actions\RattacherAgence;
use App\Domain\Organisation\Models\Agence;
use App\Domain\Organisation\Models\Organisation;
use App\Models\User;
use App\Support\MultiOrganisation\OrganisationIncoherente;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('refuse deux organisations avec le même code', function () {
    Organisation::factory()->create(['code' => 'COOPEC']);
    Organisation::factory()->create(['code' => 'COOPEC']);
})->throws(UniqueConstraintViolationException::class);

it('refuse deux agences avec le même code dans une organisation, l’accepte dans deux organisations', function () {
    Agence::factory()->for(Organisation::factory())->create(['code' => 'GOM']);
    Agence::factory()->for(Organisation::factory())->create(['code' => 'GOM']);
    expect(Agence::where('code', 'GOM')->count())->toBe(2);

    $org = Organisation::factory()->create();
    Agence::factory()->for($org)->create(['code' => 'BUK']);
    Agence::factory()->for($org)->create(['code' => 'BUK']);
})->throws(UniqueConstraintViolationException::class);

it('refuse deux utilisateurs avec le même e-mail', function () {
    User::factory()->create(['email' => 'caisse@example.com']);
    User::factory()->create(['email' => 'caisse@example.com']);
})->throws(UniqueConstraintViolationException::class);

it('rattache un utilisateur à plusieurs agences de son organisation', function () {
    $org = Organisation::factory()->create();
    $user = User::factory()->for($org)->create();
    [$a1, $a2] = Agence::factory()->for($org)->count(2)->create();

    $rattacher = new RattacherAgence;
    $rattacher->executer($user, $a1);
    $rattacher->executer($user, $a2);
    $rattacher->executer($user, $a2); // deuxième fois : sans effet, sans doublon

    expect($user->agences()->pluck('agences.id')->sort()->values()->all())->toBe([$a1->id, $a2->id])
        ->and(DB::table('agence_user')->count())->toBe(2);
});

it('refuse de rattacher un utilisateur à l’agence d’une autre organisation', function () {
    $user = User::factory()->for(Organisation::factory())->create();
    $agenceAutre = Agence::factory()->for(Organisation::factory())->create();

    (new RattacherAgence)->executer($user, $agenceAutre);
})->throws(OrganisationIncoherente::class);

it('bloque aussi en base un rattachement entre organisations (clés étrangères composées)', function () {
    $user = User::factory()->for(Organisation::factory())->create();
    $agenceAutre = Agence::factory()->for(Organisation::factory())->create();

    DB::table('agence_user')->insert([
        'organisation_id' => $user->organisation_id,
        'agence_id' => $agenceAutre->id,
        'user_id' => $user->id,
    ]);
})->throws(QueryException::class);
