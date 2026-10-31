<?php

declare(strict_types=1);

use App\Domain\Organisation\Actions\AttribuerRoles;
use App\Domain\Organisation\Models\Agence;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Tests\Support\Acteurs;

use function Pest\Laravel\actingAs;

it('laisse l’admin gérer les agences et utilisateurs de son organisation', function () {
    $admin = Acteurs::avecRole('admin');
    $agence = Agence::factory()->for($admin->organisation)->create();
    $collegue = User::factory()->for($admin->organisation)->create();
    actingAs($admin);

    expect(Gate::allows('viewAny', Agence::class))->toBeTrue()
        ->and(Gate::allows('create', Agence::class))->toBeTrue()
        ->and(Gate::allows('update', $agence))->toBeTrue()
        ->and(Gate::allows('update', $collegue))->toBeTrue()
        ->and(Gate::allows('modifierRoles', $collegue))->toBeTrue()
        ->and(Gate::allows('desactiver', $collegue))->toBeTrue()
        ->and(Gate::allows('update', $admin->organisation))->toBeTrue();
});

it('interdit à l’admin de modifier ses propres rôles ou de désactiver son propre compte', function () {
    $admin = Acteurs::avecRole('admin');
    actingAs($admin);

    expect(Gate::allows('modifierRoles', $admin))->toBeFalse()
        ->and(Gate::allows('desactiver', $admin))->toBeFalse();
});

it('laisse le gérant voir mais pas gérer', function () {
    $gerant = Acteurs::avecRole('gerant');
    $agence = Agence::factory()->for($gerant->organisation)->create();
    $collegue = User::factory()->for($gerant->organisation)->create();
    actingAs($gerant);

    expect(Gate::allows('view', $agence))->toBeTrue()
        ->and(Gate::allows('view', $collegue))->toBeTrue()
        ->and(Gate::allows('update', $agence))->toBeFalse()
        ->and(Gate::allows('create', User::class))->toBeFalse()
        ->and(Gate::allows('modifierRoles', $collegue))->toBeFalse()
        ->and(Gate::allows('update', $gerant->organisation))->toBeFalse();
});

it('refuse à un admin toute action sur une autre organisation', function () {
    $admin = Acteurs::avecRole('admin');
    $etranger = Acteurs::avecRole('caissier');
    $agenceEtrangere = Agence::factory()->for($etranger->organisation)->create();
    actingAs($admin);

    expect(Gate::allows('view', $agenceEtrangere))->toBeFalse()
        ->and(Gate::allows('update', $agenceEtrangere))->toBeFalse()
        ->and(Gate::allows('modifierRoles', $etranger))->toBeFalse()
        ->and(Gate::allows('update', $etranger->organisation))->toBeFalse();
});

it('attribue des rôles dans l’organisation de la cible', function () {
    $admin = Acteurs::avecRole('admin');
    $collegue = User::factory()->for($admin->organisation)->create();
    actingAs($admin);

    (new AttribuerRoles)->executer($admin, $collegue, ['caissier', 'collecteur']);

    expect($collegue->fresh()?->getRoleNames()->sort()->values()->all())->toBe(['caissier', 'collecteur']);
});

it('refuse d’attribuer un rôle à un utilisateur d’une autre organisation', function () {
    $admin = Acteurs::avecRole('admin');
    $etranger = Acteurs::avecRole('caissier');

    (new AttribuerRoles)->executer($admin, $etranger, ['comptable']);
})->throws(AuthorizationException::class);

it('refuse à l’admin de changer ses propres rôles', function () {
    $admin = Acteurs::avecRole('admin');

    (new AttribuerRoles)->executer($admin, $admin, ['admin', 'comptable']);
})->throws(AuthorizationException::class);

it('refuse un rôle inconnu', function () {
    $admin = Acteurs::avecRole('admin');
    $collegue = User::factory()->for($admin->organisation)->create();

    (new AttribuerRoles)->executer($admin, $collegue, ['super_admin']);
})->throws(InvalidArgumentException::class);

it('refuse à un non-admin d’attribuer des rôles', function () {
    $gerant = Acteurs::avecRole('gerant');
    $collegue = User::factory()->for($gerant->organisation)->create();

    (new AttribuerRoles)->executer($gerant, $collegue, ['caissier']);
})->throws(AuthorizationException::class);
