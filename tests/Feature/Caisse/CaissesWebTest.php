<?php

declare(strict_types=1);

use App\Domain\Caisse\Actions\OuvrirSession;
use App\Domain\Caisse\Billetage;
use App\Domain\Caisse\Models\Caisse;
use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Caisse\StatutSession;
use Tests\Support\Acteurs;
use Tests\Support\Guichet;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('laisse l’admin et le gérant créer une caisse, pas le caissier', function () {
    $g = Guichet::creer();

    actingAs($g->gerant);
    post('/caisses', ['agence_id' => $g->agence->id, 'code' => 'G2', 'libelle' => 'Guichet 2'])->assertRedirect('/caisses');
    expect(Caisse::where('code', 'G2')->exists())->toBeTrue();

    actingAs($g->caissier);
    get('/caisses/creer')->assertForbidden();
    post('/caisses', ['agence_id' => $g->agence->id, 'code' => 'G3', 'libelle' => 'Guichet 3'])->assertForbidden();
});

it('valide la création (code unique, agence de l’organisation)', function () {
    $g = Guichet::creer();
    actingAs($g->gerant);

    post('/caisses', ['agence_id' => $g->agence->id, 'code' => 'G1', 'libelle' => 'Doublon'])->assertSessionHasErrors('code');
    post('/caisses', ['agence_id' => 999999, 'code' => 'G9', 'libelle' => 'X'])->assertSessionHasErrors('agence_id');
    post('/caisses', ['agence_id' => $g->agence->id, 'code' => 'g 1!', 'libelle' => 'X'])->assertSessionHasErrors('code');
});

it('ouvre une session depuis le formulaire de billetage et affiche les totaux calculés par le serveur', function () {
    $g = Guichet::creer();
    actingAs($g->caissier);

    get("/caisses/{$g->caisse->id}/ouvrir")->assertOk()->assertSee('name="billetage[USD][100]"', false)->assertSee('name="billetage[CDF][20000]"', false);
    $reponse = post("/caisses/{$g->caisse->id}/ouvrir", ['billetage' => ['USD' => ['100' => '0'], 'CDF' => []]]);

    $session = SessionCaisse::sole();
    $reponse->assertRedirect("/sessions-caisse/{$session->id}");
    get("/sessions-caisse/{$session->id}")->assertOk()->assertSeeText('Ouverte')->assertSee('name="billetage[USD][50]"', false);
});

it('refuse une coupure inconnue', function () {
    $g = Guichet::creer();
    actingAs($g->caissier);

    post("/caisses/{$g->caisse->id}/ouvrir", ['billetage' => ['USD' => ['3' => '1']]])->assertSessionHasErrors('billetage');
    expect(SessionCaisse::count())->toBe(0);
});

it('déroule écart à l’ouverture → validation du gérant → clôture → écart → validation', function () {
    $g = Guichet::creer();
    actingAs($g->caissier);
    post("/caisses/{$g->caisse->id}/ouvrir", ['billetage' => ['USD' => ['20' => '1']]]);
    $session = SessionCaisse::sole();
    get("/sessions-caisse/{$session->id}")->assertSeeText('Écart à l’ouverture')->assertSeeText('20,00 USD')->assertDontSee('name="motif"', false);

    actingAs($g->gerant);
    get('/caisses')->assertSeeText('à valider');
    post("/sessions-caisse/{$session->id}/valider", ['motif' => 'Fonds oublié'])->assertRedirect("/sessions-caisse/{$session->id}");
    expect($session->fresh()?->statut)->toBe(StatutSession::Ouverte);

    actingAs($g->caissier);
    post("/sessions-caisse/{$session->id}/cloturer", ['billetage' => ['USD' => ['10' => '1']]])->assertRedirect();
    expect($session->fresh()?->statut)->toBe(StatutSession::EcartAJustifier);
    get("/sessions-caisse/{$session->id}")->assertSeeText('-10,00 USD');

    actingAs($g->gerant);
    post("/sessions-caisse/{$session->id}/valider", ['motif' => 'Manquant, retenue sur salaire'])->assertRedirect();
    expect($session->fresh()?->statut)->toBe(StatutSession::Fermee);
});

it('refuse la validation au caissier et sans motif', function () {
    $g = Guichet::creer();
    actingAs($g->caissier);
    $session = app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::depuisSaisie(['USD' => ['1' => 1]]));

    post("/sessions-caisse/{$session->id}/valider", ['motif' => 'moi'])->assertForbidden();
    actingAs($g->gerant);
    post("/sessions-caisse/{$session->id}/valider", ['motif' => ''])->assertSessionHasErrors('motif');
});

it('refuse la clôture à un autre que le caissier de la session', function () {
    $g = Guichet::creer();
    $session = app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());
    actingAs($g->gerant);

    post("/sessions-caisse/{$session->id}/cloturer", ['billetage' => []])->assertForbidden();
});

it('affiche un message clair si la caisse est déjà ouverte', function () {
    $g = Guichet::creer();
    app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());
    actingAs($g->gerant);

    post("/caisses/{$g->caisse->id}/ouvrir", ['billetage' => []])->assertSessionHasErrors('billetage');
});

it('répond 404 sur la session ou la caisse d’une autre organisation', function () {
    $g = Guichet::creer();
    $session = app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());
    auth()->forgetUser();
    actingAs(Acteurs::avecRole('gerant'));

    get("/sessions-caisse/{$session->id}")->assertNotFound();
    get("/caisses/{$g->caisse->id}/ouvrir")->assertNotFound();
});

it('montre l’entrée Caisses au personnel de caisse seulement', function (string $role, bool $visible) {
    actingAs(Acteurs::avecRole($role));

    $html = (string) get('/tableau-de-bord')->getContent();
    expect(str_contains($html, 'href="'.url('/caisses').'"'))->toBe($visible);
})->with([['admin', true], ['gerant', true], ['caissier', true], ['comptable', false], ['collecteur', false]]);
