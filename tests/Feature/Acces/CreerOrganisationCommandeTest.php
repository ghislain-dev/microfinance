<?php

declare(strict_types=1);

use App\Domain\Organisation\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\artisan;

it('crée une organisation et son premier admin depuis la console', function () {
    artisan('organisation:creer', ['code' => 'COOPEC-GOMA', 'nom' => 'Coopec Goma'])
        ->expectsQuestion('Nom de l’administrateur', 'Amani Kahindo')
        ->expectsQuestion('E-mail de l’administrateur', 'admin@coopec-goma.example')
        ->expectsQuestion('Mot de passe (10 caractères minimum)', 'motdepasse-solide')
        ->expectsQuestion('Confirmer le mot de passe', 'motdepasse-solide')
        ->assertSuccessful();

    $organisation = Organisation::where('code', 'COOPEC-GOMA')->sole();
    $admin = User::where('email', 'admin@coopec-goma.example')->sole();
    setPermissionsTeamId($organisation->id);

    expect($admin->organisation_id)->toBe($organisation->id)
        ->and($admin->hasRole('admin'))->toBeTrue()
        ->and(Hash::check('motdepasse-solide', $admin->password))->toBeTrue();
});

it('refuse un mot de passe trop court et ne crée rien', function () {
    artisan('organisation:creer', ['code' => 'AVEC-1', 'nom' => 'AVEC Un'])
        ->expectsQuestion('Nom de l’administrateur', 'Admin')
        ->expectsQuestion('E-mail de l’administrateur', 'admin@avec.example')
        ->expectsQuestion('Mot de passe (10 caractères minimum)', 'court')
        ->expectsQuestion('Confirmer le mot de passe', 'court')
        ->assertFailed();

    expect(Organisation::count())->toBe(0)->and(User::count())->toBe(0);
});

it('refuse un code d’organisation déjà pris', function () {
    Organisation::factory()->create(['code' => 'AVEC-1']);

    artisan('organisation:creer', ['code' => 'AVEC-1', 'nom' => 'Doublon'])->assertFailed();

    expect(Organisation::count())->toBe(1);
});
