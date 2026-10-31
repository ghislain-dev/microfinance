<?php

declare(strict_types=1);

use App\Domain\Organisation\Models\CodeOtp;
use App\Domain\Organisation\Notifications\CodeOtpNotification;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Acteurs;
use Tests\Support\Connexion;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travel;
use function Pest\Laravel\withCookie;

beforeEach(fn () => Notification::fake());

/**
 * @return array{email: string, password: string}
 */
function identifiants(string $email, string $motDePasse = 'password'): array
{
    return ['email' => $email, 'password' => $motDePasse];
}

it('affiche le formulaire de connexion', function () {
    get('/connexion')->assertOk()->assertSee('name="email"', false)->assertSee('name="_token"', false);
});

it('redirige vers la connexion sans session', function () {
    get('/tableau-de-bord')->assertRedirect('/connexion');
});

it('refuse un mauvais mot de passe sans dire si l’e-mail existe', function () {
    Event::fake([Failed::class]);
    $caissier = Acteurs::avecRole('caissier');

    post('/connexion', identifiants($caissier->email, 'mauvais-mdp'))->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);
    post('/connexion', identifiants('inconnu@example.com'))->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);

    assertGuest();
    Notification::assertNothingSent();
    Event::assertDispatched(Failed::class);
});

it('bloque la 6e tentative en une minute (429)', function () {
    $caissier = Acteurs::avecRole('caissier');

    foreach (range(1, 5) as $_) {
        post('/connexion', identifiants($caissier->email, 'mauvais-mdp'))->assertRedirect();
    }

    post('/connexion', identifiants($caissier->email, 'mauvais-mdp'))->assertTooManyRequests();
});

it('demande un code par e-mail après le mot de passe, sans connecter encore', function () {
    $caissier = Acteurs::avecRole('caissier');

    post('/connexion', identifiants($caissier->email))->assertRedirect('/connexion/code');

    assertGuest();
    Notification::assertSentTo($caissier, CodeOtpNotification::class, fn ($n, array $canaux) => $canaux === ['mail']);
    expect(Connexion::dernierCode($caissier))->toMatch('/^\d{6}$/');
});

it('connecte avec le bon code et régénère la session', function () {
    $caissier = Acteurs::avecRole('caissier');
    post('/connexion', identifiants($caissier->email));
    $sessionAvant = session()->getId();

    // Le navigateur renvoie son cookie de session : seule une régénération change l'identifiant.
    // Témoin : un code faux ne connecte pas et garde la même session.
    $code = Connexion::dernierCode($caissier);
    withCookie((string) config('session.cookie'), $sessionAvant)
        ->post('/connexion/code', ['code' => $code === '000000' ? '111111' : '000000']);
    expect(session()->getId())->toBe($sessionAvant);

    withCookie((string) config('session.cookie'), $sessionAvant)
        ->post('/connexion/code', ['code' => Connexion::dernierCode($caissier)])
        ->assertRedirect('/tableau-de-bord');

    assertAuthenticatedAs($caissier);
    expect(session()->getId())->not->toBe($sessionAvant);
    get('/tableau-de-bord')->assertOk();
});

it('exige l’OTP pour chaque rôle du personnel', function (string $role) {
    $utilisateur = Acteurs::avecRole($role);

    post('/connexion', identifiants($utilisateur->email))->assertRedirect('/connexion/code');
    assertGuest();
})->with(['admin', 'gerant', 'comptable', 'caissier', 'collecteur', 'agent_credit', 'comite']);

it('refuse la connexion web au membre (application mobile uniquement)', function () {
    $membre = Acteurs::avecRole('membre');

    post('/connexion', identifiants($membre->email))->assertSessionHasErrors('email');

    assertGuest();
    Notification::assertNothingSent();
});

it('refuse un compte désactivé ou une organisation désactivée', function () {
    $inactif = Acteurs::avecRole('caissier');
    $inactif->update(['actif' => false]);
    $orgSuspendue = Acteurs::avecRole('caissier');
    $orgSuspendue->organisation->update(['actif' => false]);

    post('/connexion', identifiants($inactif->email))->assertSessionHasErrors('email');
    post('/connexion', identifiants($orgSuspendue->email))->assertSessionHasErrors('email');

    assertGuest();
    Notification::assertNothingSent();
});

it('invalide le code après 3 essais faux, même si le 4e est juste', function () {
    $caissier = Acteurs::avecRole('caissier');
    post('/connexion', identifiants($caissier->email));
    $code = Connexion::dernierCode($caissier);
    $faux = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 3) as $_) {
        post('/connexion/code', ['code' => $faux])->assertSessionHasErrors('code');
    }
    post('/connexion/code', ['code' => $code])->assertSessionHasErrors('code');

    assertGuest();
});

it('refuse un code expiré (plus de 10 minutes)', function () {
    $caissier = Acteurs::avecRole('caissier');
    post('/connexion', identifiants($caissier->email));
    $code = Connexion::dernierCode($caissier);

    travel(11)->minutes();

    post('/connexion/code', ['code' => $code])->assertRedirect('/connexion');
    assertGuest();
});

it('refuse de réutiliser un code', function () {
    $caissier = Acteurs::avecRole('caissier');
    post('/connexion', identifiants($caissier->email));
    $code = Connexion::dernierCode($caissier);
    post('/connexion/code', ['code' => $code]);
    post('/deconnexion');

    post('/connexion', identifiants($caissier->email));
    Notification::fake(); // ignorer le nouveau code : on tente l'ancien
    post('/connexion/code', ['code' => $code])->assertSessionHasErrors('code');

    assertGuest();
});

it('ne stocke jamais le code en clair', function () {
    $caissier = Acteurs::avecRole('caissier');
    post('/connexion', identifiants($caissier->email));
    $code = Connexion::dernierCode($caissier);

    $ligne = CodeOtp::sole();
    expect($ligne->code_hache)->not->toContain($code)
        ->and($ligne->organisation_id)->toBe($caissier->organisation_id);
});

it('renvoie un nouveau code au plus une fois par minute, l’ancien devient invalide', function () {
    $caissier = Acteurs::avecRole('caissier');
    post('/connexion', identifiants($caissier->email));
    $ancien = Connexion::dernierCode($caissier);

    post('/connexion/code/renvoyer')->assertSessionHasErrors('code'); // trop tôt
    travel(61)->seconds();
    Notification::fake();
    post('/connexion/code/renvoyer')->assertRedirect('/connexion/code');
    $nouveau = Connexion::dernierCode($caissier);

    if ($ancien !== $nouveau) {
        post('/connexion/code', ['code' => $ancien])->assertSessionHasErrors('code');
    }
    post('/connexion/code', ['code' => $nouveau])->assertRedirect('/tableau-de-bord');
});

it('renvoie vers la connexion si aucun code n’est en attente', function () {
    get('/connexion/code')->assertRedirect('/connexion');
    post('/connexion/code', ['code' => '123456'])->assertRedirect('/connexion');
});

it('limite les essais de code à 5 par minute (429)', function () {
    $caissier = Acteurs::avecRole('caissier');
    post('/connexion', identifiants($caissier->email));

    foreach (range(1, 5) as $_) {
        post('/connexion/code', ['code' => 'abc']);
    }

    post('/connexion/code', ['code' => 'abc'])->assertTooManyRequests();
});

it('déconnecte et invalide la session', function () {
    $caissier = Acteurs::avecRole('caissier');
    post('/connexion', identifiants($caissier->email));
    post('/connexion/code', ['code' => Connexion::dernierCode($caissier)]);
    $session = session()->getId();

    post('/deconnexion')->assertRedirect('/connexion');

    assertGuest();
    expect(session()->getId())->not->toBe($session);
});
