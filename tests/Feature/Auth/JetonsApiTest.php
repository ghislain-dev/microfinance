<?php

declare(strict_types=1);

use App\Domain\Organisation\Notifications\CodeOtpNotification;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\Acteurs;
use Tests\Support\Connexion;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

beforeEach(fn () => Notification::fake());

/**
 * Connexion en deux temps et renvoi du jeton.
 */
function jetonPour(User $utilisateur, string $appareil = 'Tecno Spark 10'): string
{
    $demande = ['email' => $utilisateur->email, 'password' => 'password', 'appareil' => $appareil];
    postJson('/api/v1/auth/jetons', $demande)->assertAccepted();

    return (string) postJson('/api/v1/auth/jetons', [...$demande, 'code' => Connexion::dernierCode($utilisateur)])
        ->assertCreated()
        ->json('jeton');
}

it('demande d’abord un code (202), puis délivre un jeton (201)', function () {
    $collecteur = Acteurs::avecRole('collecteur');

    postJson('/api/v1/auth/jetons', ['email' => $collecteur->email, 'password' => 'password', 'appareil' => 'Itel A70'])
        ->assertAccepted()
        ->assertJson(['statut' => 'code_requis']);
    Notification::assertSentTo($collecteur, CodeOtpNotification::class);

    $jeton = jetonPour($collecteur);
    getJson('/api/v1/moi', ['Authorization' => "Bearer {$jeton}"])
        ->assertOk()
        ->assertJsonPath('data.email', $collecteur->email)
        ->assertJsonMissingPath('data.password');
});

it('refuse un mauvais mot de passe ou un mauvais code (422), sans fuite technique', function () {
    $collecteur = Acteurs::avecRole('collecteur');

    postJson('/api/v1/auth/jetons', ['email' => $collecteur->email, 'password' => 'faux', 'appareil' => 'X'])
        ->assertUnprocessable()->assertJsonValidationErrors('email')->assertJsonMissingPath('trace');
    postJson('/api/v1/auth/jetons', ['email' => $collecteur->email, 'password' => 'password', 'appareil' => 'X', 'code' => '000000'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');

    expect(PersonalAccessToken::count())->toBe(0);
});

it('limite les demandes de jeton à 5 par minute (429)', function () {
    $collecteur = Acteurs::avecRole('collecteur');

    foreach (range(1, 5) as $_) {
        postJson('/api/v1/auth/jetons', ['email' => $collecteur->email, 'password' => 'faux', 'appareil' => 'X']);
    }

    postJson('/api/v1/auth/jetons', ['email' => $collecteur->email, 'password' => 'faux', 'appareil' => 'X'])->assertTooManyRequests();
});

it('refuse un jeton expiré', function () {
    $jeton = jetonPour(Acteurs::avecRole('collecteur'));

    travel((int) config('sanctum.expiration') + 1)->minutes();

    getJson('/api/v1/moi', ['Authorization' => "Bearer {$jeton}"])->assertUnauthorized();
});

it('liste les appareils et révoque un jeton', function () {
    $collecteur = Acteurs::avecRole('collecteur');
    $telephone = jetonPour($collecteur, 'Téléphone terrain');
    $tablette = jetonPour($collecteur, 'Tablette bureau');

    $appareils = getJson('/api/v1/appareils', ['Authorization' => "Bearer {$telephone}"])->assertOk()->collect('data');
    expect($appareils->pluck('nom')->sort()->values()->all())->toBe(['Tablette bureau', 'Téléphone terrain']);

    $idTablette = $appareils->firstWhere('nom', 'Tablette bureau')['id'];
    deleteJson("/api/v1/appareils/{$idTablette}", [], ['Authorization' => "Bearer {$telephone}"])->assertNoContent();

    app('auth')->forgetGuards();
    getJson('/api/v1/moi', ['Authorization' => "Bearer {$tablette}"])->assertUnauthorized();
});

it('ne révoque pas le jeton d’un autre utilisateur (404)', function () {
    $moi = jetonPour(Acteurs::avecRole('collecteur'));
    $autre = Acteurs::avecRole('collecteur');
    jetonPour($autre);
    $idAutre = PersonalAccessToken::where('tokenable_id', $autre->id)->value('id');

    app('auth')->forgetGuards();
    deleteJson("/api/v1/appareils/{$idAutre}", [], ['Authorization' => "Bearer {$moi}"])->assertNotFound();
    expect(PersonalAccessToken::whereKey($idAutre)->exists())->toBeTrue();
});

it('révoque le jeton courant à la déconnexion', function () {
    $jeton = jetonPour(Acteurs::avecRole('collecteur'));

    postJson('/api/v1/auth/deconnexion', [], ['Authorization' => "Bearer {$jeton}"])->assertNoContent();

    app('auth')->forgetGuards();
    getJson('/api/v1/moi', ['Authorization' => "Bearer {$jeton}"])->assertUnauthorized();
});

it('refuse un compte désactivé, même avec un jeton déjà délivré', function () {
    $collecteur = Acteurs::avecRole('collecteur');
    $jeton = jetonPour($collecteur);

    $collecteur->update(['actif' => false]);

    app('auth')->forgetGuards();
    getJson('/api/v1/moi', ['Authorization' => "Bearer {$jeton}"])->assertUnauthorized();
});
