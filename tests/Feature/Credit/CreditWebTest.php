<?php

declare(strict_types=1);

use App\Domain\Credit\Actions\DecaisserPret;
use App\Domain\Credit\Models\GarantiePret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\ProduitCredit;
use App\Domain\Credit\Models\Remboursement;
use App\Domain\Credit\Models\SeuilCredit;
use App\Domain\Credit\StatutPret;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Credit;
use Tests\Support\Epargnant;
use Tests\Support\Guichet;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * @param  array<string, mixed>  $autres
 * @return array<string, mixed>
 */
function formulaireProduitCredit(array $autres = []): array
{
    return [
        'code' => 'AGR', 'libelle' => 'Crédit agricole', 'devise' => 'USD', 'taux_mensuel' => '2,5', 'periodicite' => 'hebdomadaire',
        'duree_max' => '52', 'montant_minimum' => '50', 'montant_maximum' => '5000', 'anciennete_mois' => '3', 'multiple_epargne' => '2',
        'garantie_epargne' => '10', 'penalite_jour' => '0,25', 'delai_grace_jours' => '2', ...$autres,
    ];
}

/**
 * @param  array<string, mixed>  $autres
 * @return array<string, mixed>
 */
function formulaireDemande(Credit $c, array $autres = []): array
{
    return [
        'membre_id' => $c->membre->id, 'produit_credit_id' => $c->produit->id, 'compte_epargne_id' => $c->compte->id,
        'montant' => '1000', 'nombre_echeances' => '10', 'methode' => 'lineaire', 'objet' => 'Achat de marchandises',
        'garanties' => [], ...$autres,
    ];
}

it('paramètre un produit de crédit et le seuil du gérant depuis les écrans', function () {
    $g = Guichet::creer();
    actingAs($g->gerant);

    get('/credit/produits')->assertOk();
    get('/credit/produits/creer')->assertOk();
    post('/credit/produits', formulaireProduitCredit(['taux_mensuel' => 'abc', 'garantie_epargne' => '150', 'periodicite' => 'jamais']))
        ->assertSessionHasErrors(['taux_mensuel', 'garantie_epargne', 'periodicite']);
    post('/credit/produits', formulaireProduitCredit())->assertRedirect('/credit/produits');

    $produit = ProduitCredit::sole();
    expect($produit->taux_mensuel_pb)->toBe(250)->and($produit->penalite_jour_pb)->toBe(25);
    get("/credit/produits/{$produit->id}/modifier")->assertOk()->assertSee('value="2,50"', false);
    put("/credit/produits/{$produit->id}", formulaireProduitCredit(['taux_mensuel' => '3', 'actif' => '0']))->assertRedirect('/credit/produits');
    expect($produit->refresh()->taux_mensuel_pb)->toBe(300)->and($produit->actif)->toBeFalse();

    post('/credit/seuils', ['devise' => 'USD', 'montant' => '2000'])->assertRedirect('/credit/produits');
    expect(SeuilCredit::sole()->montant->formate())->toBe('2 000,00 USD');
    get('/credit/produits')->assertSeeText('2 000,00 USD')->assertSeeText('3,00 %');
});

it('mène un prêt de la demande au décaissement depuis les écrans', function () {
    Storage::fake('local');
    $c = Credit::preparer();
    $caution = Epargnant::membre($c->g);

    actingAs($c->agent);
    get("/credit/demandes/creer?membre={$c->membre->numero}")->assertOk()->assertSeeText($c->compte->numero)->assertSeeText('Crédit PME');
    post('/credit/demandes', formulaireDemande($c, ['garanties' => [
        ['type' => 'caution', 'membre_numero' => $caution->numero, 'montant' => '300'],
        ['type' => 'bien', 'description' => 'Moto Haojue', 'valeur' => '800', 'piece' => UploadedFile::fake()->create('facture.pdf', 100, 'application/pdf')],
        ['type' => 'caution', 'membre_numero' => '', 'montant' => ''], // ligne vide ignorée
    ]]))->assertRedirect();

    $pret = Pret::sole();
    $bien = GarantiePret::where('type', 'bien')->sole();
    expect(GarantiePret::count())->toBe(2)
        ->and($bien->getAttribute('piece_chemin'))->toStartWith('garanties/');
    Storage::disk('local')->assertExists((string) $bien->getAttribute('piece_chemin'));
    get("/garanties/{$bien->id}/piece")->assertOk();

    post("/prets/{$pret->id}/analyse", ['analyse' => 'Commerce rentable.'])->assertRedirect("/prets/{$pret->id}");

    actingAs($c->comite);
    get("/prets/{$pret->id}")->assertOk()->assertSeeText('Approuver')->assertSeeText('Moto Haojue')->assertSeeText($caution->numero);
    post("/prets/{$pret->id}/approuver", ['motif' => 'Accordé'])->assertRedirect("/prets/{$pret->id}");

    actingAs($c->comptable);
    post("/prets/{$pret->id}/decaissement")->assertRedirect("/prets/{$pret->id}");
    expect($pret->refresh()->statut)->toBe(StatutPret::EnCours);
    get("/prets/{$pret->id}")->assertOk()->assertSeeText('En cours')->assertSeeText('30,00 USD')->assertSeeText('200,00 USD');
});

it('rembourse depuis la fiche du prêt, sans doublon si le formulaire est renvoyé', function () {
    $c = Credit::preparer();
    $pret = $c->approuve();
    app(DecaisserPret::class)->executer($c->comptable, $pret);
    actingAs($c->g->caissier);

    get("/prets/{$pret->id}")->assertOk()->assertSee('name="cle"', false);
    $cle = (string) Str::uuid();
    post("/prets/{$pret->id}/remboursements", ['montant' => 'abc', 'mode' => 'especes', 'cle' => $cle])->assertSessionHasErrors('montant');
    foreach ([1, 2] as $envoi) {
        post("/prets/{$pret->id}/remboursements", ['montant' => '130', 'mode' => 'especes', 'cle' => $cle])->assertRedirect("/prets/{$pret->id}");
    }
    post("/prets/{$pret->id}/remboursements", ['montant' => '5000', 'mode' => 'especes', 'cle' => (string) Str::uuid()])
        ->assertSessionHasErrors('montant');

    expect(Remboursement::count())->toBe(1);
    get("/prets/{$pret->id}")->assertSeeText('130,00 USD');
});

it('liste les prêts avec le PAR 30 et le PAR 90', function () {
    $c = Credit::preparer();
    $pret = $c->demande();
    actingAs($c->comite);

    get('/credit')->assertOk()->assertSeeText($pret->numero)->assertSeeText('En analyse')->assertSeeText('PAR 30')->assertSeeText('PAR 90');
    get('/credit?statut=soumis')->assertOk()->assertDontSeeText($pret->numero);
});

it('affiche les refus métier et valide les garanties saisies', function () {
    $c = Credit::preparer();
    actingAs($c->agent);

    post('/credit/demandes', formulaireDemande($c, ['montant' => '1500,01']))->assertSessionHasErrors('demande');
    post('/credit/demandes', formulaireDemande($c, ['garanties' => [['type' => 'caution', 'membre_numero' => 'INCONNU', 'montant' => '100']]]))
        ->assertSessionHasErrors('garanties');
    post('/credit/demandes', formulaireDemande($c, ['garanties' => [['type' => 'caution', 'membre_numero' => $c->membre->numero, 'montant' => 'abc']]]))
        ->assertSessionHasErrors('garanties.0.montant');
    post('/credit/demandes', formulaireDemande($c, ['garanties' => [['type' => 'bien', 'description' => 'Moto',
        'piece' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]]]))->assertSessionHasErrors('garanties.0.piece');
    post('/credit/demandes', formulaireDemande($c, ['methode' => 'magique', 'nombre_echeances' => 'x']))->assertSessionHasErrors(['methode', 'nombre_echeances']);
    expect(Pret::count())->toBe(0);
});

it('protège les écrans par les droits et l’organisation', function () {
    $c = Credit::preparer();
    $pret = $c->approuve();
    $collecteur = Acteurs::avecRole('collecteur', $c->g->organisation);

    actingAs($collecteur);
    get('/credit/produits')->assertForbidden();
    actingAs($c->g->caissier);
    post('/credit/demandes', formulaireDemande($c))->assertForbidden();
    actingAs($c->agent);
    post("/prets/{$pret->id}/decaissement")->assertForbidden();
    get('/tableau-de-bord')->assertSeeText('Crédit');

    auth()->forgetUser();
    $ailleurs = Acteurs::avecRole('gerant');
    actingAs($ailleurs);
    get("/prets/{$pret->id}")->assertNotFound();
});
