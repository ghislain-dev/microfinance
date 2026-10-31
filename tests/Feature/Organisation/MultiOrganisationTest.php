<?php

declare(strict_types=1);

use App\Domain\Organisation\Models\Agence;
use App\Domain\Organisation\Models\Organisation;
use App\Models\User;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use App\Support\MultiOrganisation\OrganisationIncoherente;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\Route;
use Tests\Support\DeuxOrganisations;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('ne montre que les données de l’organisation de l’utilisateur connecté', function () {
    $o = DeuxOrganisations::creer();
    actingAs($o->userA);

    expect(Agence::pluck('id')->all())->toBe([$o->agenceA->id])
        ->and(User::pluck('id')->all())->toBe([$o->userA->id])
        ->and(Agence::find($o->agenceB->id))->toBeNull();
});

it('voit toutes les organisations sans utilisateur connecté (console, commandes planifiées)', function () {
    DeuxOrganisations::creer();

    expect(Agence::count())->toBe(2)
        ->and(User::count())->toBe(2);
});

it('remplit organisation_id depuis l’utilisateur connecté à la création', function () {
    $o = DeuxOrganisations::creer();
    actingAs($o->userA);

    $agence = Agence::create(['nom' => 'Agence Matonge', 'code' => 'MAT']);

    expect($agence->organisation_id)->toBe($o->orgA->id);
});

it('refuse organisation_id en assignation de masse (jamais depuis la requête)', function () {
    $o = DeuxOrganisations::creer();
    actingAs($o->userA);

    Agence::create(['nom' => 'Pirate', 'code' => 'PIR', 'organisation_id' => $o->orgB->id]);
})->throws(MassAssignmentException::class);

it('refuse de créer une ligne pour une autre organisation que celle de l’utilisateur', function () {
    $o = DeuxOrganisations::creer();
    actingAs($o->userA);

    $agence = new Agence(['nom' => 'Pirate', 'code' => 'PIR']);
    $agence->organisation_id = $o->orgB->id;
    $agence->save();
})->throws(OrganisationIncoherente::class);

it('interdit de déplacer une ligne vers une autre organisation', function () {
    $o = DeuxOrganisations::creer();
    $o->agenceA->organisation_id = $o->orgB->id;
    $o->agenceA->save();
})->throws(OrganisationIncoherente::class);

it('répond 404 sur une agence ou un utilisateur d’une autre organisation', function () {
    $o = DeuxOrganisations::creer();
    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('/_test/agences/{agence}', fn (Agence $agence) => $agence->code);
        Route::get('/_test/utilisateurs/{user}', fn (User $user) => $user->email);
    });
    actingAs($o->userA);

    get("/_test/agences/{$o->agenceA->id}")->assertOk();
    get("/_test/agences/{$o->agenceB->id}")->assertNotFound();
    get("/_test/utilisateurs/{$o->userA->id}")->assertOk();
    get("/_test/utilisateurs/{$o->userB->id}")->assertNotFound();
});

it('applique le trait AppartientAOrganisation à tout modèle métier (sauf Organisation)', function () {
    $sansTrait = [];
    foreach (glob(app_path('Domain/*/Models/*.php')) ?: [] as $fichier) {
        $classe = 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($fichier, strlen(app_path()) + 1));
        if ($classe !== Organisation::class
            && ! in_array(AppartientAOrganisation::class, class_uses_recursive($classe), true)) {
            $sansTrait[] = $classe;
        }
    }

    expect($sansTrait)->toBe([])
        ->and(class_uses_recursive(User::class))->toContain(AppartientAOrganisation::class);
});
