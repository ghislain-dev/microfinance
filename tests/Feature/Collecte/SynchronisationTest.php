<?php

declare(strict_types=1);

use App\Domain\Caisse\Billetage;
use App\Domain\Collecte\Actions\AffecterMembre;
use App\Domain\Collecte\Actions\EnregistrerVersement;
use App\Domain\Collecte\Actions\FixerParametresCollecte;
use App\Domain\Collecte\Actions\ValiderVersement;
use App\Domain\Collecte\Models\Appareil;
use App\Domain\Collecte\Models\OperationSync;
use App\Domain\Collecte\Models\VersementCollecte;
use App\Domain\Collecte\Services\CaisseCollecteur;
use App\Domain\Credit\Actions\DecaisserPret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\Models\Ecriture;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Tontines\Actions\SaisirSeance;
use App\Domain\Tontines\Models\Cotisation;
use App\Support\Montant\Devise;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Acteurs;
use Tests\Support\Collecte;
use Tests\Support\Credit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

/**
 * @param  list<array<string, mixed>>  $operations
 * @return TestResponse<Response>
 */
function envoyerLot(string $jeton, array $operations, ?string $lot = null): TestResponse
{
    auth()->forgetGuards();

    return postJson('/api/v1/sync/lots', ['lot_uuid' => $lot ?? (string) Str::uuid(), 'operations' => $operations], ['Authorization' => "Bearer {$jeton}"]);
}

/**
 * @param  array<string, mixed>  $autres
 * @return array<string, mixed>
 */
function operationSync(string $type, array $autres = []): array
{
    return ['cle' => (string) Str::uuid(), 'type' => $type, 'saisi_le' => now()->subMinutes(30)->toIso8601String(), ...$autres];
}

function especesCollecteur(Collecte $c, Devise $devise = Devise::USD): string
{
    $caisse = app(CaisseCollecteur::class)->de($c->collecteur);

    return $caisse === null ? '—' : app(MoteurFinancier::class)->soldeReel($caisse->compte($devise))->formate();
}

function soldeSync(Compte $compte): string
{
    return app(MoteurFinancier::class)->soldeReel($compte)->formate();
}

it('enregistre un dépôt collecté : caisse du collecteur et épargne augmentent, reçu officiel émis', function () {
    $c = Collecte::preparer();
    $jeton = $c->jeton();
    $op = operationSync('depot', ['membre_id' => $c->membres[0]->id, 'compte_epargne_id' => $c->comptes[0]->id, 'montant_centimes' => 2_550]);

    envoyerLot($jeton, [$op])->assertOk()
        ->assertJsonPath('resultats.0.cle', $op['cle'])
        ->assertJsonPath('resultats.0.statut', 'cree')
        ->assertJsonPath('resultats.0.recu', fn ($n) => is_string($n) && $n !== '');

    expect(soldeSync($c->comptes[0]->compte))->toBe('125,50 USD')
        ->and(especesCollecteur($c))->toBe('25,50 USD')
        ->and(MouvementEpargne::where('type', 'depot')->latest('id')->first()?->getAttribute('session_caisse_id'))->toBeNull()
        ->and(OperationSync::sole()->getAttribute('saisi_le'))->not->toBeNull()
        ->and((int) Ecriture::sum('debit'))->toBe((int) Ecriture::sum('credit'));
});

it('est idempotent : un lot renvoyé répond « deja_traite » sans rien créer de plus', function () {
    $c = Collecte::preparer();
    $jeton = $c->jeton();
    $lot = (string) Str::uuid();
    $op = operationSync('depot', ['membre_id' => $c->membres[0]->id, 'compte_epargne_id' => $c->comptes[0]->id, 'montant_centimes' => 1_000]);

    envoyerLot($jeton, [$op], $lot)->assertJsonPath('resultats.0.statut', 'cree');
    for ($i = 0; $i < 5; $i++) {
        envoyerLot($jeton, [$op], $lot)->assertJsonPath('resultats.0.statut', 'deja_traite')->assertJsonPath('resultats.0.operation_id', fn ($id) => $id > 0);
    }

    expect(soldeSync($c->comptes[0]->compte))->toBe('110,00 USD')->and(OperationSync::count())->toBe(1);
});

it('répond opération par opération : les refus n’empêchent pas les autres', function () {
    $c = Collecte::preparer();
    $jeton = $c->jeton();
    $horsTournee = $c->horsTournee;

    $reponse = envoyerLot($jeton, [
        operationSync('depot', ['membre_id' => $c->membres[0]->id, 'compte_epargne_id' => $c->comptes[0]->id, 'montant_centimes' => 500]),
        operationSync('depot', ['membre_id' => $horsTournee->id, 'compte_epargne_id' => $c->comptes[0]->id, 'montant_centimes' => 500]),
        operationSync('depot', ['membre_id' => $c->membres[0]->id, 'compte_epargne_id' => $c->comptes[1]->id, 'montant_centimes' => 500]),
        operationSync('virement', ['membre_id' => $c->membres[0]->id]),
        operationSync('depot', ['membre_id' => $c->membres[1]->id, 'compte_epargne_id' => $c->comptes[1]->id, 'montant_centimes' => -5]),
    ])->assertOk();

    expect($reponse->json('resultats.*.statut'))->toBe(['cree', 'rejete', 'rejete', 'rejete', 'rejete'])
        ->and($reponse->json('resultats.1.raison'))->toContain('tournée')
        ->and(especesCollecteur($c))->toBe('5,00 USD');
});

it('limite les retraits : plafond hors-ligne, solde disponible, jamais au-delà du seuil 4 yeux', function () {
    $c = Collecte::preparer(); // plafond de retrait 50 USD, épargne 100 USD
    $jeton = $c->jeton();
    $retrait = fn (int $centimes, int $i = 0) => operationSync('retrait', ['membre_id' => $c->membres[$i]->id, 'compte_epargne_id' => $c->comptes[$i]->id, 'montant_centimes' => $centimes]);

    // Le collecteur a d'abord collecté 60 USD (espèces pour payer les retraits).
    envoyerLot($jeton, [operationSync('depot', ['membre_id' => $c->membres[1]->id, 'compte_epargne_id' => $c->comptes[1]->id, 'montant_centimes' => 6_000])]);
    $reponse = envoyerLot($jeton, [$retrait(5_001), $retrait(4_000), $retrait(4_000)]);

    expect($reponse->json('resultats.*.statut'))->toBe(['rejete', 'cree', 'rejete'])
        ->and($reponse->json('resultats.0.raison'))->toContain('plafond')
        ->and(soldeSync($c->comptes[0]->compte))->toBe('60,00 USD')
        ->and(especesCollecteur($c))->toBe('20,00 USD');
});

it('encaisse un remboursement de prêt collecté, imputé comme au guichet', function () {
    $credit = Credit::preparer();
    $pret = $credit->approuve('1000', 10);
    app(DecaisserPret::class)->executer($credit->comptable, $pret);
    // L'emprunteur entre dans la tournée d'un collecteur de la même organisation.
    $collecteur = Acteurs::avecRole('collecteur', $credit->g->organisation);
    app(AffecterMembre::class)->executer($credit->g->gerant, $collecteur, $credit->membre);
    app(FixerParametresCollecte::class)->executer($credit->g->gerant, 24, ['USD' => ['plafond_retrait' => '50', 'plafond_especes' => '1000']]);
    $c2 = new Collecte($credit->g, $collecteur, [$credit->membre], [$credit->compte], $credit->membre);
    $jeton = $c2->jeton();

    envoyerLot($jeton, [operationSync('remboursement', ['membre_id' => $credit->membre->id, 'pret_id' => $pret->id, 'montant_centimes' => 13_000])])
        ->assertJsonPath('resultats.0.statut', 'cree');

    expect(soldeSync(Pret::findOrFail($pret->id)->comptePret()->firstOrFail()))->toBe('900,00 USD')
        ->and(especesCollecteur($c2))->toBe('130,00 USD');
});

it('enregistre une cotisation pour la prochaine séance : la feuille la reprend sans double encaissement', function () {
    [$g, $cycle, $membres] = cycleEnCours();
    $collecteur = Acteurs::avecRole('collecteur', $g->organisation);
    foreach ($membres as $m) {
        app(AffecterMembre::class)->executer($g->gerant, $collecteur, $m);
    }
    app(FixerParametresCollecte::class)->executer($g->gerant, 24, ['CDF' => ['plafond_retrait' => '10000', 'plafond_especes' => '1000000']]);
    $c = new Collecte($g, $collecteur, $membres, [], $membres[0]);
    $jeton = $c->jeton();
    $cotisation = operationSync('cotisation', ['membre_id' => $membres[0]->id, 'cycle_id' => $cycle->id, 'montant_centimes' => 1_000_000, 'fonds_social_centimes' => 50_000]);

    envoyerLot($jeton, [$cotisation])->assertJsonPath('resultats.0.statut', 'cree');
    envoyerLot($jeton, [operationSync('cotisation', ['membre_id' => $membres[0]->id, 'cycle_id' => $cycle->id, 'montant_centimes' => 1_000_000, 'fonds_social_centimes' => 0])])
        ->assertJsonPath('resultats.0.statut', 'rejete'); // déjà payée pour cette séance
    expect(especesCollecteur($c, Devise::CDF))->toBe('10 500,00 CDF');

    // À la séance, le caissier saisit la feuille : la cotisation déjà collectée n'est pas encaissée une seconde fois.
    auth()->setUser($g->caissier);
    app(SaisirSeance::class)->executer($g->caissier, seance($cycle, 1), feuille($membres));
    expect(Cotisation::where('membre_id', $membres[0]->id)->whereIn('type', ['cotisation', 'fonds_social'])->count())->toBe(2)
        ->and(soldeSync($g->caisse->compte(Devise::CDF)))->toBe('21 000,00 CDF') // deux autres membres : 2 × (10 000 + 500)
        ->and(soldeSync($cycle->compteFonds))->toBe('0,00 CDF'); // 30 000 encaissés puis versés au bénéficiaire
});

it('verse les espèces du collecteur au guichet : sans écart, transfert immédiat', function () {
    $c = Collecte::preparer();
    $jeton = $c->jeton();
    envoyerLot($jeton, [operationSync('depot', ['membre_id' => $c->membres[0]->id, 'compte_epargne_id' => $c->comptes[0]->id, 'montant_centimes' => 3_000])]);
    auth()->setUser($c->g->caissier);

    $versements = app(EnregistrerVersement::class)->executer($c->g->caissier, $c->collecteur, Billetage::depuisSaisie(['USD' => [20 => 1, 10 => 1]]));

    expect($versements)->toHaveCount(1)
        ->and($versements[0]->statut)->toBe('valide')
        ->and(especesCollecteur($c))->toBe('0,00 USD')
        ->and(soldeSync($c->g->caisse->compte(Devise::USD)))->toBe('330,00 USD'); // 300 déposés à la préparation + 30
});

it('écart au versement : bloqué jusqu’à la validation du gérant, manquant à la charge du collecteur', function () {
    $c = Collecte::preparer();
    $jeton = $c->jeton();
    envoyerLot($jeton, [operationSync('depot', ['membre_id' => $c->membres[0]->id, 'compte_epargne_id' => $c->comptes[0]->id, 'montant_centimes' => 3_000])]);
    auth()->setUser($c->g->caissier);

    [$versement] = app(EnregistrerVersement::class)->executer($c->g->caissier, $c->collecteur, Billetage::depuisSaisie(['USD' => [20 => 1, 5 => 1]]));
    expect($versement->statut)->toBe('ecart_a_justifier')
        ->and($versement->ecart->formate())->toBe('-5,00 USD')
        ->and(especesCollecteur($c))->toBe('30,00 USD');

    expect(fn () => app(ValiderVersement::class)->executer($c->g->caissier, $versement, 'ok'))->toThrow(AuthorizationException::class);
    app(ValiderVersement::class)->executer($c->g->gerant, $versement, 'Billet de 5 perdu');

    expect($versement->refresh()->statut)->toBe('valide')
        ->and(especesCollecteur($c))->toBe('0,00 USD')
        ->and(soldeSync($c->g->caisse->compte(Devise::USD)))->toBe('325,00 USD')
        ->and(soldeSync(Compte::where('numero', "CREANCE-COLLECTEUR-{$c->collecteur->id}-USD")->sole()))->toBe('5,00 USD');
    artisan('finance:verifier-soldes')->assertSuccessful();
});

it('enregistre la dernière synchronisation du téléphone et refuse un lot vide ou mal formé', function () {
    $c = Collecte::preparer();
    $jeton = $c->jeton();

    envoyerLot($jeton, [])->assertUnprocessable();
    auth()->forgetGuards();
    postJson('/api/v1/sync/lots', ['lot_uuid' => 'x', 'operations' => [['cle' => 'pas-uuid']]], ['Authorization' => "Bearer {$jeton}"])
        ->assertUnprocessable()->assertJsonValidationErrors(['lot_uuid', 'operations.0.cle', 'operations.0.type']);
    envoyerLot($jeton, [operationSync('depot', ['membre_id' => $c->membres[0]->id, 'compte_epargne_id' => $c->comptes[0]->id, 'montant_centimes' => 100])]);

    expect(Appareil::sole()->derniere_sync_le)->not->toBeNull();
});

it('reçoit un versement depuis l’écran du guichet et affiche les refus au gérant', function () {
    $c = Collecte::preparer();
    $jeton = $c->jeton();
    envoyerLot($jeton, [
        operationSync('depot', ['membre_id' => $c->membres[0]->id, 'compte_epargne_id' => $c->comptes[0]->id, 'montant_centimes' => 3_000]),
        operationSync('depot', ['membre_id' => $c->horsTournee->id, 'compte_epargne_id' => $c->comptes[0]->id, 'montant_centimes' => 100]),
    ]);

    actingAs($c->g->caissier);
    get('/versements')->assertOk()->assertSeeText('30,00 USD');
    post('/versements', ['collecteur_id' => $c->collecteur->id, 'billetage' => ['USD' => ['20' => 1, '5' => 1]]])->assertRedirect('/versements');
    $versement = VersementCollecte::sole();
    expect($versement->statut)->toBe('ecart_a_justifier');
    post("/versements/{$versement->id}/valider", ['motif' => 'moi'])->assertForbidden();

    actingAs($c->g->gerant);
    get('/collecte')->assertOk()->assertSeeText('Membre hors de votre tournée.');
    post("/versements/{$versement->id}/valider", ['motif' => 'Billet perdu'])->assertRedirect('/versements');
    expect($versement->refresh()->statut)->toBe('valide');
});
