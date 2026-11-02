<?php

declare(strict_types=1);

use App\Domain\Credit\Actions\DecaisserPret;
use App\Domain\Epargne\Actions\Deposer;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Membres\Actions\CreerAccesMobile;
use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Actions\SaisirSeance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\Acteurs;
use Tests\Support\Credit;
use Tests\Support\Epargnant;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

/**
 * Accès créé au guichet, mot de passe changé : renvoie le jeton du membre.
 */
function jetonMembre(Membre $membre, User $guichetier): string
{
    [, $provisoire] = app(CreerAccesMobile::class)->executer($guichetier, $membre);
    auth()->forgetGuards(); // l'API ne doit voir que le jeton, pas l'utilisateur du guichet
    $jeton = (string) postJson('/api/v1/auth/membre', [
        'organisation' => $membre->organisation?->code, 'numero' => $membre->numero, 'password' => $provisoire, 'appareil' => 'Itel A70',
    ])->assertCreated()->json('jeton');
    postJson('/api/v1/moi/mot-de-passe', ['actuel' => $provisoire, 'nouveau' => 'Kinshasa2026', 'nouveau_confirmation' => 'Kinshasa2026'],
        ['Authorization' => "Bearer {$jeton}"])->assertNoContent();
    auth()->forgetGuards();

    return $jeton;
}

/**
 * @return array<string, string>
 */
function porteur(string $jeton): array
{
    return ['Authorization' => "Bearer {$jeton}"];
}

it('crée l’accès mobile d’un membre au guichet (gérant, caissier) avec un mot de passe provisoire', function () {
    $c = Credit::preparer();

    [$utilisateur, $provisoire] = app(CreerAccesMobile::class)->executer($c->g->caissier, $c->membre);

    expect($utilisateur->getAttribute('membre_id'))->toBe($c->membre->id)
        ->and($utilisateur->email)->toBeNull()
        ->and($utilisateur->getAttribute('mot_de_passe_provisoire'))->toBeTrue()
        ->and(Hash::check($provisoire, $utilisateur->password))->toBeTrue()
        ->and(strlen($provisoire))->toBeGreaterThanOrEqual(10)
        ->and($utilisateur->hasRole('membre'))->toBeTrue()
        ->and(fn () => app(CreerAccesMobile::class)->executer(Acteurs::avecRole('collecteur', $c->g->organisation), $c->membre))
        ->toThrow(AuthorizationException::class);
});

it('réinitialise l’accès : nouveau mot de passe provisoire, anciens jetons révoqués', function () {
    $c = Credit::preparer();
    $jeton = jetonMembre($c->membre, $c->g->caissier);

    [, $nouveau] = app(CreerAccesMobile::class)->executer($c->g->gerant, $c->membre);
    auth()->forgetGuards();

    expect(PersonalAccessToken::count())->toBe(0)
        ->and(User::where('membre_id', $c->membre->id)->count())->toBe(1);
    getJson('/api/v1/membre/comptes', porteur($jeton))->assertUnauthorized();
    postJson('/api/v1/auth/membre', ['organisation' => $c->g->organisation->code, 'numero' => $c->membre->numero, 'password' => $nouveau, 'appareil' => 'X'])
        ->assertCreated()->assertJsonPath('doit_changer_mot_de_passe', true);
});

it('connecte le membre par organisation + numéro + mot de passe et impose le changement du mot de passe provisoire', function () {
    $c = Credit::preparer();
    [, $provisoire] = app(CreerAccesMobile::class)->executer($c->g->caissier, $c->membre);
    auth()->forgetGuards();
    $identifiants = ['organisation' => $c->g->organisation->code, 'numero' => $c->membre->numero, 'appareil' => 'Tecno'];

    postJson('/api/v1/auth/membre', [...$identifiants, 'password' => 'faux'])->assertUnprocessable()->assertJsonValidationErrors('numero');
    postJson('/api/v1/auth/membre', [...$identifiants, 'organisation' => 'AUTRE', 'password' => $provisoire])->assertUnprocessable();
    $jeton = (string) postJson('/api/v1/auth/membre', [...$identifiants, 'password' => $provisoire])
        ->assertCreated()->assertJsonPath('doit_changer_mot_de_passe', true)->json('jeton');

    getJson('/api/v1/membre/comptes', porteur($jeton))->assertForbidden()->assertJsonPath('code', 'mot_de_passe_a_changer');
    postJson('/api/v1/moi/mot-de-passe', ['actuel' => 'faux', 'nouveau' => 'Kinshasa2026', 'nouveau_confirmation' => 'Kinshasa2026'], porteur($jeton))
        ->assertUnprocessable()->assertJsonValidationErrors('actuel');
    postJson('/api/v1/moi/mot-de-passe', ['actuel' => $provisoire, 'nouveau' => 'court', 'nouveau_confirmation' => 'court'], porteur($jeton))
        ->assertUnprocessable()->assertJsonValidationErrors('nouveau');
    postJson('/api/v1/moi/mot-de-passe', ['actuel' => $provisoire, 'nouveau' => 'Kinshasa2026', 'nouveau_confirmation' => 'Kinshasa2026'], porteur($jeton))
        ->assertNoContent();
    auth()->forgetGuards();

    getJson('/api/v1/membre/comptes', porteur($jeton))->assertOk();
    postJson('/api/v1/auth/membre', [...$identifiants, 'password' => 'Kinshasa2026'])->assertCreated()->assertJsonPath('doit_changer_mot_de_passe', false);
});

it('limite les tentatives de connexion des membres', function () {
    $c = Credit::preparer();
    app(CreerAccesMobile::class)->executer($c->g->caissier, $c->membre);
    $essai = ['organisation' => $c->g->organisation->code, 'numero' => $c->membre->numero, 'password' => 'faux', 'appareil' => 'X'];

    foreach (range(1, 5) as $i) {
        postJson('/api/v1/auth/membre', $essai)->assertUnprocessable();
    }
    postJson('/api/v1/auth/membre', $essai)->assertTooManyRequests();
});

it('donne au membre ses comptes, soldes en centimes (jamais de nombre à virgule) et mouvements depuis une date', function () {
    $c = Credit::preparer();
    $jeton = jetonMembre($c->membre, $c->g->caissier);

    getJson('/api/v1/membre/comptes', porteur($jeton))->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.numero', $c->compte->numero)
        ->assertJsonPath('data.0.solde', ['centimes' => 50_000, 'devise' => 'USD', 'texte' => '500,00 USD'])
        ->assertJsonPath('data.0.disponible.centimes', 50_000);

    $repere = now()->toIso8601String();
    travel(5)->minutes();
    auth()->setUser($c->g->caissier);
    app(Deposer::class)->executer($c->g->caissier, $c->compte, Credit::usd('25'), (string) Str::uuid());
    auth()->forgetGuards();

    getJson("/api/v1/membre/comptes/{$c->compte->id}/mouvements", porteur($jeton))->assertOk()->assertJsonCount(2, 'data');
    getJson("/api/v1/membre/comptes/{$c->compte->id}/mouvements?depuis=".urlencode($repere), porteur($jeton))->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'depot')
        ->assertJsonPath('data.0.montant.centimes', 2_500);
    getJson("/api/v1/membre/comptes/{$c->compte->id}/mouvements?depuis=hier", porteur($jeton))->assertUnprocessable();
});

it('ne montre jamais les données d’un autre membre', function () {
    $c = Credit::preparer();
    $autre = Epargnant::membre($c->g);
    $compteAutre = app(OuvrirCompteEpargne::class)->executer($c->g->gerant, $autre, $c->compte->type);
    $jeton = jetonMembre($c->membre, $c->g->caissier);

    getJson("/api/v1/membre/comptes/{$compteAutre->id}/mouvements", porteur($jeton))->assertNotFound();
    getJson('/api/v1/membre/comptes', porteur($jeton))->assertJsonMissing(['numero' => $compteAutre->numero]);
});

it('donne au membre ses prêts et leurs échéances', function () {
    $c = Credit::preparer();
    $pret = $c->approuve('1000', 10);
    app(DecaisserPret::class)->executer($c->comptable, $pret);
    $jeton = jetonMembre($c->membre, $c->g->caissier);

    getJson('/api/v1/membre/prets', porteur($jeton))->assertOk()
        ->assertJsonPath('data.0.numero', $pret->numero)
        ->assertJsonPath('data.0.statut', 'en_cours')
        ->assertJsonPath('data.0.reste_du.centimes', 116_500)
        ->assertJsonCount(10, 'data.0.echeances')
        ->assertJsonPath('data.0.echeances.0.interet.centimes', 3_000);
});

it('donne au membre ses tontines : prochaine séance, son tour et ses dettes', function () {
    [$g, $cycle, $membres] = cycleEnCours(parametres: ['mode_tours' => 'ordre_fixe'], ordre: [1, 0, 2]);
    app(SaisirSeance::class)->executer($g->caissier, seance($cycle, 1), feuille($membres, [$membres[0]->id => ['presence' => 'absent', 'cotisation' => '0', 'fonds_social' => '0']]));
    $jeton = jetonMembre($membres[0], $g->caissier);

    getJson('/api/v1/membre/tontines', porteur($jeton))->assertOk()
        ->assertJsonPath('data.0.cycle_id', $cycle->id)
        ->assertJsonPath('data.0.prochaine_seance.rang', 2)
        ->assertJsonPath('data.0.mon_tour.rang', 2)
        ->assertJsonPath('data.0.dette.centimes', 1_350_000); // cotisation 10 000 + FS 500 + absence 1 000 + impayé 2 000
});

it('réserve les routes membre aux jetons membre', function () {
    $c = Credit::preparer();
    $collecteur = Acteurs::avecRole('collecteur', $c->g->organisation);
    $jeton = $collecteur->createToken('test', ['collecteur'])->plainTextToken;
    auth()->forgetGuards();

    getJson('/api/v1/membre/comptes', porteur($jeton))->assertForbidden();
});

it('crée l’accès depuis la fiche du membre et n’affiche le mot de passe qu’une fois', function () {
    $c = Credit::preparer();
    actingAs($c->g->caissier);

    get("/membres/{$c->membre->id}")->assertOk()->assertSeeText('Créer l’accès');
    post("/membres/{$c->membre->id}/acces-mobile")->assertRedirect("/membres/{$c->membre->id}")->assertSessionHas('acces_mobile.mot_de_passe');
    get("/membres/{$c->membre->id}")->assertOk()->assertSeeText('Mot de passe provisoire'); // l'écran qui suit
    get("/membres/{$c->membre->id}")->assertOk()->assertSeeText('Réinitialiser le mot de passe')->assertDontSeeText('Mot de passe provisoire');

    actingAs(Acteurs::avecRole('comptable', $c->g->organisation));
    post("/membres/{$c->membre->id}/acces-mobile")->assertForbidden();
});
