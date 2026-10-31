<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Acteurs;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('laisse l’admin envoyer le logo, stocké en privé sous un nom aléatoire', function () {
    Storage::fake('local');
    $admin = Acteurs::avecRole('admin');
    actingAs($admin);

    get('/organisation')->assertOk()->assertSee('name="logo"', false);
    post('/organisation/logo', ['logo' => UploadedFile::fake()->image('Mon Logo.png', 200, 80)])->assertRedirect('/organisation');

    $chemin = (string) $admin->organisation->fresh()?->logo_chemin;
    expect($chemin)->toStartWith("organisations/{$admin->organisation_id}/")
        ->and(str_contains($chemin, 'Mon Logo'))->toBeFalse();
    Storage::disk('local')->assertExists($chemin);
});

it('remplace l’ancien logo', function () {
    Storage::fake('local');
    $admin = Acteurs::avecRole('admin');
    actingAs($admin);
    post('/organisation/logo', ['logo' => UploadedFile::fake()->image('a.png')]);
    $ancien = (string) $admin->organisation->fresh()?->logo_chemin;

    post('/organisation/logo', ['logo' => UploadedFile::fake()->image('b.jpg')]);

    Storage::disk('local')->assertMissing($ancien);
});

it('refuse un fichier qui n’est pas une image PNG/JPEG ou trop lourd', function (UploadedFile $fichier) {
    Storage::fake('local');
    actingAs(Acteurs::avecRole('admin'));

    post('/organisation/logo', ['logo' => $fichier])->assertSessionHasErrors('logo');
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
    'svg' => fn () => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    'trop lourd' => fn () => UploadedFile::fake()->image('logo.png')->size(600),
]);

it('réserve l’envoi du logo à l’admin', function () {
    Storage::fake('local');
    actingAs(Acteurs::avecRole('gerant'));

    get('/organisation')->assertForbidden();
    post('/organisation/logo', ['logo' => UploadedFile::fake()->image('a.png')])->assertForbidden();
});
