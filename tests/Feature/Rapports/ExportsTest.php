<?php

declare(strict_types=1);

use App\Domain\Credit\Actions\DecaisserPret;
use App\Domain\Epargne\Actions\Deposer;
use App\Domain\Epargne\Actions\Retirer;
use App\Domain\Membres\Actions\CreerAccesMobile;
use App\Domain\Rapports\Actions\DemanderExport;
use App\Domain\Rapports\Models\Export;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Credit;
use Tests\Support\Epargnant;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travelTo;

beforeEach(fn () => Storage::fake('local'));

/**
 * Épargne : 500 le 10/01, +100 le 20/01, −30 le 05/02.
 */
function exerciceExports(): Credit
{
    travelTo('2026-01-10 09:00');
    $c = Credit::preparer();
    travelTo('2026-01-20 09:00');
    app(Deposer::class)->executer($c->g->caissier, $c->compte, Credit::usd('100'), (string) Str::uuid());
    travelTo('2026-02-05 09:00');
    app(Retirer::class)->executer($c->g->caissier, $c->compte, Credit::usd('30'), (string) Str::uuid());
    travelTo('2026-03-01 09:00');

    return $c;
}

function contenuExport(Export $export): string
{
    $export->refresh();
    expect($export->statut)->toBe('pret');

    return (string) Storage::disk('local')->get((string) $export->getAttribute('chemin'));
}

it('exporte la balance en CSV (point-virgule, UTF-8), générée en file d’attente', function () {
    $c = exerciceExports();

    $export = app(DemanderExport::class)->executer($c->comptable, 'balance', 'csv', ['devise' => 'USD', 'du' => '2026-02-01', 'au' => '2026-02-28']);
    $csv = contenuExport($export);

    expect($csv)->toStartWith("\u{FEFF}") // BOM : Excel ouvre les accents correctement
        ->and($csv)->toContain('Compte;Libellé;Solde d’ouverture;Débit;Crédit;Solde de clôture')
        ->and($csv)->toContain('5711;Caisses;600,00;0,00;30,00;570,00')
        ->and($csv)->toContain('Totaux;;;30,00;30,00;');
});

it('exporte en PDF', function () {
    $c = exerciceExports();

    $export = app(DemanderExport::class)->executer($c->g->gerant, 'grand_livre', 'pdf', ['rubrique' => 'caisse', 'devise' => 'USD', 'du' => '2026-01-01', 'au' => '2026-02-28']);

    expect(contenuExport($export))->toStartWith('%PDF');
});

it('établit le relevé d’un compte membre : solde de début, mouvements avec solde après, solde de fin', function () {
    $c = exerciceExports();

    $csv = contenuExport(app(DemanderExport::class)->executer($c->g->caissier, 'releve', 'csv', [
        'compte_epargne_id' => $c->compte->id, 'du' => '2026-01-15', 'au' => '2026-02-28',
    ]));

    expect($csv)->toContain('Solde au 14/01/2026;;;;500,00')
        ->and($csv)->toContain('20/01/2026;Dépôt;100,00;;600,00')
        ->and($csv)->toContain('05/02/2026;Retrait;;30,00;570,00')
        ->and($csv)->toContain('Solde au 28/02/2026;;;;570,00');
});

it('exporte le portefeuille de crédit avec retards et PAR', function () {
    travelTo('2026-01-15 09:00');
    $c = Credit::preparer();
    app(DecaisserPret::class)->executer($c->comptable, $c->approuve('1000', 10));
    travelTo('2026-03-20 09:00');

    $csv = contenuExport(app(DemanderExport::class)->executer($c->agent, 'portefeuille', 'csv', ['devise' => 'USD']));

    expect($csv)->toContain($c->membre->numero.'-CR1')
        ->and($csv)->toContain('1 000,00')   // encours
        ->and($csv)->toContain(';33;')       // jours de retard (échéance du 15/02)
        ->and($csv)->toContain('PAR 30;100,00 %');
});

it('exporte la caisse et la collecte d’une période', function () {
    $c = exerciceExports();

    $csv = contenuExport(app(DemanderExport::class)->executer($c->comptable, 'caisse', 'csv', ['du' => '2026-01-01', 'au' => '2026-02-28']));

    expect($csv)->toContain('G1')->toContain('Ouverte');
});

it('réserve chaque export à ses droits et chaque fichier à son auteur', function () {
    $c = exerciceExports();

    expect(fn () => app(DemanderExport::class)->executer($c->g->caissier, 'balance', 'csv', ['devise' => 'USD', 'du' => '2026-02-01', 'au' => '2026-02-28']))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(DemanderExport::class)->executer($c->agent, 'caisse', 'csv', ['du' => '2026-01-01', 'au' => '2026-02-28']))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(DemanderExport::class)->executer($c->comptable, 'balance', 'csv', ['devise' => 'EUR', 'du' => 'x', 'au' => '2026-02-28']))
        ->toThrow(InvalidArgumentException::class);

    $export = app(DemanderExport::class)->executer($c->comptable, 'journal', 'csv', ['devise' => 'USD', 'du' => '2026-02-01', 'au' => '2026-02-28']);
    actingAs($c->comptable);
    get("/exports/{$export->id}/fichier")->assertOk()->assertHeader('content-disposition');
    actingAs($c->g->gerant);
    get("/exports/{$export->id}/fichier")->assertNotFound();
});

it('demande et liste les exports depuis l’écran', function () {
    $c = exerciceExports();
    actingAs($c->comptable);

    get('/exports')->assertOk()->assertSeeText('Balance');
    post('/exports', ['type' => 'balance', 'format' => 'pdf', 'devise' => 'USD', 'du' => '2026-02-01', 'au' => '2026-02-28'])->assertRedirect('/exports');
    post('/exports', ['type' => 'inconnu', 'format' => 'pdf'])->assertSessionHasErrors('type');
    get('/exports')->assertOk()->assertSeeText('Télécharger');
});

it('donne au membre son relevé dans l’application', function () {
    $c = exerciceExports();
    [$utilisateur] = app(CreerAccesMobile::class)->executer($c->g->caissier, $c->membre);
    $utilisateur->forceFill(['mot_de_passe_provisoire' => false])->save();
    $jeton = $utilisateur->createToken('t', ['membre'])->plainTextToken;
    auth()->forgetGuards();
    $entete = ['Authorization' => "Bearer {$jeton}"];

    $id = postJson("/api/v1/membre/comptes/{$c->compte->id}/releves", ['du' => '2026-01-01', 'au' => '2026-02-28'], $entete)
        ->assertAccepted()->json('id');
    getJson("/api/v1/membre/releves/{$id}", $entete)->assertOk()->assertHeader('content-type', 'application/pdf');

    auth()->setUser($c->g->caissier);
    [$voisin] = app(CreerAccesMobile::class)->executer($c->g->caissier, Epargnant::membre($c->g));
    $voisin->forceFill(['mot_de_passe_provisoire' => false])->save();
    $autre = $voisin->createToken('x', ['membre'])->plainTextToken;
    auth()->forgetGuards();
    getJson("/api/v1/membre/releves/{$id}", ['Authorization' => "Bearer {$autre}"])->assertNotFound();
});

it('propose le relevé PDF depuis la page du compte d’épargne', function () {
    $c = exerciceExports();
    actingAs($c->g->caissier);

    get("/comptes-epargne/{$c->compte->id}")->assertOk()->assertSeeText('Relevé PDF');
    post('/exports', ['type' => 'releve', 'format' => 'pdf', 'compte_epargne_id' => $c->compte->id, 'du' => '2026-01-01', 'au' => '2026-02-28'])
        ->assertRedirect('/exports');
    expect(Export::sole()->statut)->toBe('pret');
});
