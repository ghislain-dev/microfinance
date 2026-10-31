<?php

declare(strict_types=1);

use App\Domain\Membres\Actions\InscrireMembre;
use App\Domain\Membres\Models\Membre;
use App\Domain\Membres\StatutMembre;
use App\Domain\Organisation\Models\Agence;
use App\Domain\Organisation\Models\Organisation;
use App\Models\User;
use Tests\Support\Acteurs;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * @param  array<string, mixed>  $autres
 * @return array<string, mixed>
 */
function formulaire(Agence $agence, array $autres = []): array
{
    return [
        'agence_id' => $agence->id,
        'nom' => 'Kahindo', 'postnom' => 'Masika', 'prenom' => 'Esther',
        'sexe' => 'F', 'date_naissance' => '1990-05-12',
        'telephone' => '0812223344', 'piece_type' => 'Carte d’électeur', 'piece_numero' => 'CE-55667788',
        'adresse' => 'Goma, Himbi',
        ...$autres,
    ];
}

/**
 * @return array{User, Agence}
 */
function caissierAuGuichet(): array
{
    $caissier = Acteurs::avecRole('caissier');
    $agence = Agence::factory()->for($caissier->organisation)->create(['code' => 'BUK', 'nom' => 'Agence Bukavu']);
    actingAs($caissier);

    return [$caissier, $agence];
}

it('inscrit un membre depuis le formulaire et affiche sa fiche', function () {
    [, $agence] = caissierAuGuichet();

    $reponse = post('/membres', formulaire($agence));

    $membre = Membre::sole();
    $reponse->assertRedirect("/membres/{$membre->id}");
    get("/membres/{$membre->id}")->assertOk()
        ->assertSeeText('BUK-000001')
        ->assertSeeText('Kahindo Masika Esther')
        ->assertSeeText('+243812223344')
        ->assertSeeText('Actif')
        ->assertDontSeeText('CE-55667788')  // pièce masquée sur la fiche
        ->assertSeeText('7788');
});

it('valide la saisie (champs obligatoires, sexe, date, téléphone)', function () {
    [, $agence] = caissierAuGuichet();

    post('/membres', formulaire($agence, ['nom' => '', 'sexe' => 'X', 'date_naissance' => now()->addDay()->toDateString(), 'telephone' => '12', 'piece_numero' => '']))
        ->assertSessionHasErrors(['nom', 'sexe', 'date_naissance', 'telephone', 'piece_numero']);

    expect(Membre::count())->toBe(0);
});

it('refuse une agence d’une autre organisation', function () {
    [$caissier] = caissierAuGuichet();
    auth()->forgetUser();
    $autre = Agence::factory()->create();
    actingAs($caissier);

    post('/membres', formulaire($autre))->assertSessionHasErrors('agence_id');
    expect(Membre::count())->toBe(0);
});

it('ignore un organisation_id envoyé par le formulaire', function () {
    [$caissier, $agence] = caissierAuGuichet();
    auth()->forgetUser();
    $autreOrg = Organisation::factory()->create();
    actingAs($caissier);

    post('/membres', formulaire($agence, ['organisation_id' => $autreOrg->id]))->assertRedirect();

    expect(Membre::sole()->organisation_id)->toBe($caissier->organisation_id);
});

it('refuse une pièce déjà enregistrée avec le numéro du membre existant', function () {
    [, $agence] = caissierAuGuichet();
    post('/membres', formulaire($agence));

    post('/membres', formulaire($agence, ['telephone' => '0990000000', 'piece_numero' => 'ce 55667788']))
        ->assertSessionHasErrors(['piece_numero' => 'Cette pièce d\'identité est déjà enregistrée pour le membre BUK-000001.']);

    expect(Membre::count())->toBe(1);
});

it('signale un téléphone partagé et inscrit après confirmation', function () {
    [, $agence] = caissierAuGuichet();
    post('/membres', formulaire($agence));

    post('/membres', formulaire($agence, ['piece_numero' => 'CE-2']))
        ->assertSessionHasErrors('telephone')
        ->assertSessionHas('telephone_partage');
    expect(Membre::count())->toBe(1);

    post('/membres', formulaire($agence, ['piece_numero' => 'CE-2', 'confirmer_telephone' => '1']))->assertRedirect();
    expect(Membre::count())->toBe(2);
});

it('liste, cherche et pagine les membres de l’organisation seulement', function () {
    [$caissier, $agence] = caissierAuGuichet();
    $inscrire = app(InscrireMembre::class);
    foreach (range(1, 30) as $i) {
        $inscrire->executer($caissier, $agence, formulaire($agence, [
            'nom' => $i === 7 ? 'Zawadi' : 'Membre', 'telephone' => '0970'.str_pad((string) $i, 6, '0', STR_PAD_LEFT), 'piece_numero' => "P-{$i}",
        ]));
    }
    auth()->forgetUser();
    Membre::factory()->create(['nom' => 'Etranger']);
    actingAs($caissier);

    get('/membres')->assertOk()->assertSeeText('BUK-000001')->assertDontSeeText('BUK-000030')->assertDontSeeText('Etranger');
    get('/membres?page=2')->assertOk()->assertSeeText('BUK-000030');
    get('/membres?q=zaw')->assertOk()->assertSeeText('BUK-000007')->assertDontSeeText('BUK-000001');
    get('/membres?q=BUK-000012')->assertOk()->assertSeeText('BUK-000012')->assertDontSeeText('BUK-000011');
    get('/membres?q=0970000005')->assertOk()->assertSeeText('BUK-000005')->assertDontSeeText('BUK-000006');
});

it('modifie une fiche et change le statut', function () {
    [$caissier, $agence] = caissierAuGuichet();
    $membre = app(InscrireMembre::class)->executer($caissier, $agence, formulaire($agence));

    get("/membres/{$membre->id}/modifier")->assertOk()->assertSee('CE-55667788', false);
    put("/membres/{$membre->id}", formulaire($agence, ['nom' => 'Kavira']))->assertRedirect("/membres/{$membre->id}");
    patch("/membres/{$membre->id}/statut", ['statut' => 'suspendu'])->assertRedirect("/membres/{$membre->id}");

    expect($membre->fresh()?->nom)->toBe('Kavira')
        ->and($membre->fresh()?->statut)->toBe(StatutMembre::Suspendu)
        ->and($membre->fresh()?->numero)->toBe('BUK-000001');
});

it('laisse le collecteur consulter mais pas inscrire ni modifier', function () {
    [$caissier, $agence] = caissierAuGuichet();
    $membre = app(InscrireMembre::class)->executer($caissier, $agence, formulaire($agence));
    actingAs(Acteurs::avecRole('collecteur', $caissier->organisation));

    get('/membres')->assertOk()->assertDontSee('href="'.url('/membres/creer').'"', false);
    get("/membres/{$membre->id}")->assertOk()->assertDontSeeText('Modifier');
    get('/membres/creer')->assertForbidden();
    post('/membres', formulaire($agence, ['piece_numero' => 'Z']))->assertForbidden();
    get("/membres/{$membre->id}/modifier")->assertForbidden();
    put("/membres/{$membre->id}", formulaire($agence))->assertForbidden();
    patch("/membres/{$membre->id}/statut", ['statut' => 'exclu'])->assertForbidden();
});

it('répond 404 sur un membre d’une autre organisation', function () {
    $etranger = Membre::factory()->create();
    caissierAuGuichet();

    get("/membres/{$etranger->id}")->assertNotFound();
    get("/membres/{$etranger->id}/modifier")->assertNotFound();
    patch("/membres/{$etranger->id}/statut", ['statut' => 'exclu'])->assertNotFound();
});

it('refuse un statut inconnu', function () {
    [$caissier, $agence] = caissierAuGuichet();
    $membre = app(InscrireMembre::class)->executer($caissier, $agence, formulaire($agence));

    patch("/membres/{$membre->id}/statut", ['statut' => 'radie'])->assertSessionHasErrors('statut');
});

it('ajoute Membres au menu pour tout le personnel', function () {
    caissierAuGuichet();

    get('/tableau-de-bord')->assertOk()->assertSee('href="'.url('/membres').'"', false);
});
