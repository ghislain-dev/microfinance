<?php

declare(strict_types=1);

use App\Domain\Caisse\Actions\OuvrirSession;
use App\Domain\Caisse\Billetage;
use App\Domain\Collecte\Actions\AffecterMembre;
use App\Domain\Collecte\Actions\DeciderAppareil;
use App\Domain\Collecte\Actions\FixerParametresCollecte;
use App\Domain\Collecte\Actions\RetirerMembre;
use App\Domain\Collecte\CollecteRefusee;
use App\Domain\Collecte\Models\Appareil;
use App\Domain\Collecte\Services\CaisseCollecteur;
use App\Domain\Epargne\Actions\Deposer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\Acteurs;
use Tests\Support\Collecte;
use Tests\Support\Credit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

/**
 * @return array<string, string>
 */
function bearer(string $jeton): array
{
    return ['Authorization' => "Bearer {$jeton}"];
}

it('ne délivre un jeton au collecteur que depuis un téléphone autorisé par le gérant', function () {
    $c = Collecte::preparer();
    Notification::fake();
    auth()->forgetGuards();
    $demande = ['email' => $c->collecteur->email, 'password' => 'password', 'appareil' => 'Itel A70'];

    postJson('/api/v1/auth/jetons', $demande)->assertUnprocessable()->assertJsonValidationErrors('appareil_id');
    postJson('/api/v1/auth/jetons', [...$demande, 'appareil_id' => 'pas-un-uuid'])->assertUnprocessable();
    postJson('/api/v1/auth/jetons', [...$demande, 'appareil_id' => (string) Str::uuid()])
        ->assertForbidden()->assertJsonPath('code', 'appareil_en_attente');
    Notification::assertNothingSent();

    $appareil = Appareil::sole();
    expect($appareil->statut)->toBe('en_attente')->and($appareil->getAttribute('user_id'))->toBe($c->collecteur->id);
    expect(fn () => app(DeciderAppareil::class)->autoriser($c->g->caissier, $appareil))->toThrow(AuthorizationException::class);

    travel(2)->minutes(); // au-delà de la limite de 5 tentatives par minute
    $jeton = $c->jeton((string) $appareil->getAttribute('identifiant'));
    expect(PersonalAccessToken::findToken($jeton)?->abilities)->toBe(['collecteur']);
    getJson('/api/v1/collecte/tournee', bearer($jeton))->assertOk();
});

it('révoquer le téléphone coupe immédiatement ses jetons', function () {
    $c = Collecte::preparer();
    $jeton = $c->jeton();

    app(DeciderAppareil::class)->revoquer($c->g->gerant, Appareil::sole());
    auth()->forgetGuards();

    getJson('/api/v1/collecte/tournee', bearer($jeton))->assertUnauthorized();
    postJson('/api/v1/auth/jetons', ['email' => $c->collecteur->email, 'password' => 'password', 'appareil' => 'Itel A70',
        'appareil_id' => Appareil::sole()->getAttribute('identifiant')])->assertForbidden()->assertJsonPath('code', 'appareil_revoque');
});

it('donne au collecteur sa tournée : membres affectés seulement, soldes, paramètres et espèces détenues', function () {
    $c = Collecte::preparer();
    $jeton = $c->jeton();

    $reponse = getJson('/api/v1/collecte/tournee', bearer($jeton))->assertOk()
        ->assertJsonCount(2, 'membres')
        ->assertJsonPath('parametres.delai_max_heures', 24)
        ->assertJsonPath('parametres.plafonds.USD.plafond_retrait.centimes', 5_000)
        ->assertJsonPath('parametres.plafonds.USD.seuil_validation.centimes', 20_000)
        ->assertJsonPath('especes_detenues.USD.centimes', 0)
        ->assertJsonPath('membres.0.comptes.0.solde.centimes', 10_000)
        ->assertJsonPath('membres.0.comptes.0.disponible.centimes', 10_000);
    expect($reponse->json('membres.*.numero'))->toBe([$c->membres[0]->numero, $c->membres[1]->numero])
        ->and($reponse->json('serveur_le'))->not->toBeNull();
});

it('ne renvoie depuis une date que les membres modifiés et ceux retirés de la tournée', function () {
    $c = Collecte::preparer();
    $jeton = $c->jeton();
    $repere = getJson('/api/v1/collecte/tournee', bearer($jeton))->json('serveur_le');
    travel(1)->minutes();

    auth()->setUser($c->g->caissier);
    app(Deposer::class)->executer($c->g->caissier, $c->comptes[1], Credit::usd('5'), (string) Str::uuid());
    app(RetirerMembre::class)->executer($c->g->gerant, $c->collecteur, $c->membres[0]);
    auth()->forgetGuards();

    getJson('/api/v1/collecte/tournee?depuis='.urlencode((string) $repere), bearer($jeton))->assertOk()
        ->assertJsonCount(1, 'membres')
        ->assertJsonPath('membres.0.numero', $c->membres[1]->numero)
        ->assertJsonPath('membres.0.comptes.0.solde.centimes', 10_500)
        ->assertJsonPath('membres_retires', [$c->membres[0]->id]);
});

it('refuse un plafond de retrait au-delà du seuil de validation 4 yeux', function () {
    $c = Collecte::preparer();

    expect(fn () => app(FixerParametresCollecte::class)->executer($c->g->gerant, 24, [
        'USD' => ['plafond_retrait' => '200,01', 'plafond_especes' => '1000'],
    ]))->toThrow(CollecteRefusee::class, 'seuil');
});

it('réserve l’affectation au gérant et aux membres de son organisation', function () {
    $c = Collecte::preparer();

    expect(fn () => app(AffecterMembre::class)->executer($c->g->caissier, $c->collecteur, $c->horsTournee))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AffecterMembre::class)->executer($c->g->gerant, $c->g->caissier, $c->horsTournee))->toThrow(CollecteRefusee::class);
});

it('sépare les jetons : collecteur hors de l’espace membre, membre hors de la collecte', function () {
    $c = Collecte::preparer();
    $jeton = $c->jeton();
    $jetonMembre = Acteurs::avecRole('membre', $c->g->organisation)->createToken('m', ['membre'])->plainTextToken;
    auth()->forgetGuards();

    getJson('/api/v1/membre/comptes', bearer($jeton))->assertForbidden();
    auth()->forgetGuards();
    getJson('/api/v1/collecte/tournee', bearer($jetonMembre))->assertForbidden();
});

it('gère téléphones, tournées et paramètres depuis l’écran Collecte (gérant)', function () {
    $c = Collecte::preparer();
    Notification::fake();
    auth()->forgetGuards();
    postJson('/api/v1/auth/jetons', ['email' => $c->collecteur->email, 'password' => 'password', 'appareil' => 'Tecno Pop 7',
        'appareil_id' => (string) Str::uuid()])->assertForbidden();
    $appareil = Appareil::sole();

    actingAs($c->g->gerant);
    get('/collecte')->assertOk()->assertSeeText('Tecno Pop 7')->assertSeeText($c->membres[0]->numero);
    post("/collecte/appareils/{$appareil->id}/autoriser")->assertRedirect('/collecte');
    expect($appareil->refresh()->statut)->toBe('autorise');
    post("/collecte/{$c->collecteur->id}/tournee", ['numero' => $c->horsTournee->numero])->assertRedirect('/collecte');
    post("/collecte/{$c->collecteur->id}/tournee", ['numero' => 'INCONNU'])->assertSessionHasErrors('numero');
    post('/collecte/parametres', ['delai_max_heures' => '12', 'plafonds' => [
        'USD' => ['plafond_retrait' => '40', 'plafond_especes' => '800'], 'CDF' => ['plafond_retrait' => 'abc', 'plafond_especes' => '1'],
    ]])->assertSessionHasErrors('plafonds.CDF.plafond_retrait');

    actingAs($c->g->caissier);
    get('/collecte')->assertForbidden();
});

it('n’ouvre jamais la caisse d’un collecteur au guichet et ne la liste pas', function () {
    $c = Collecte::preparer();
    $caisse = app(CaisseCollecteur::class)->de($c->collecteur);

    expect($caisse)->not->toBeNull()
        ->and(fn () => app(OuvrirSession::class)->executer($c->g->gerant, $caisse, Billetage::vide()))
        ->toThrow(AuthorizationException::class);
    actingAs($c->g->gerant);
    get('/caisses')->assertOk()->assertDontSeeText((string) $caisse?->getAttribute('code'));
});
