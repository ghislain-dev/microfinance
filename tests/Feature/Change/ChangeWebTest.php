<?php

declare(strict_types=1);

use App\Domain\Change\Actions\SaisirTaux;
use App\Domain\Change\Models\Conversion;
use App\Domain\Change\Models\TauxChange;
use App\Domain\Change\Taux;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\MoteurFinancier;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Epargnant;
use Tests\Support\GrandLivre;
use Tests\Support\Guichet;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('laisse le gérant et le comptable saisir le taux du jour', function () {
    $g = Guichet::creer();
    actingAs(Acteurs::avecRole('comptable', $g->organisation));

    get('/change')->assertOk()->assertSee('name="achat"', false);
    post('/change', ['achat' => '2 800', 'vente' => '2900,5'])->assertRedirect('/change');

    $taux = TauxChange::sole();
    expect($taux->achat()->formate())->toBe('2 800,000000')->and($taux->vente()->formate())->toBe('2 900,500000');
    get('/change')->assertSeeText('2 800,000000')->assertSeeText('2 850,250000');
});

it('valide la saisie du taux', function () {
    $g = Guichet::creer();
    actingAs($g->gerant);

    post('/change', ['achat' => '0', 'vente' => 'abc'])->assertSessionHasErrors(['achat', 'vente']);
    post('/change', ['achat' => '2900', 'vente' => '2800'])->assertSessionHasErrors('achat');
    expect(TauxChange::count())->toBe(0);
});

it('montre le taux au caissier sans lui permettre de le saisir', function () {
    $g = Guichet::creer();
    actingAs($g->caissier);

    get('/change')->assertOk()->assertDontSee('name="achat"', false)->assertSeeText('Aucun taux');
    post('/change', ['achat' => '2800', 'vente' => '2900'])->assertForbidden();
});

it('convertit depuis la page du compte', function () {
    $g = Guichet::creer();
    $membre = Epargnant::membre($g);
    $usd = app(OuvrirCompteEpargne::class)->executer($g->gerant, $membre, Epargnant::type($g->gerant, 'EPU', Devise::USD));
    $cdf = app(OuvrirCompteEpargne::class)->executer($g->gerant, $membre, Epargnant::type($g->gerant, 'EPC', Devise::CDF));
    actingAs($g->gerant);
    $cent = Montant::depuisSaisie('100', Devise::USD);
    app(MoteurFinancier::class)->executer('alim', 'essai', [LigneEcriture::debit(GrandLivre::compte($g->gerant, NatureCompte::Produit), $cent), LigneEcriture::credit($usd->compte, $cent)]);
    app(SaisirTaux::class)->executer($g->gerant, Taux::depuisSaisie('2800'), Taux::depuisSaisie('2900'));
    actingAs($g->caissier);

    get("/comptes-epargne/{$usd->id}")->assertOk()->assertSee('name="destination_id"', false)->assertSeeText($cdf->numero)->assertSeeText('2 800,000000');
    post("/comptes-epargne/{$usd->id}/conversions", ['montant' => '10', 'destination_id' => $cdf->id, 'cle' => (string) Str::uuid()])
        ->assertRedirect("/comptes-epargne/{$usd->id}")->assertSessionHas('statut');

    expect(Conversion::sole()->montant_destination->formate())->toBe('28 000,00 CDF');
    get("/comptes-epargne/{$cdf->id}")->assertSeeText('28 000,00 CDF')->assertSeeText('Change (devise reçue)');
});

it('affiche un refus clair sans taux du jour', function () {
    $g = Guichet::creer();
    $membre = Epargnant::membre($g);
    $usd = app(OuvrirCompteEpargne::class)->executer($g->gerant, $membre, Epargnant::type($g->gerant, 'EPU', Devise::USD));
    $cdf = app(OuvrirCompteEpargne::class)->executer($g->gerant, $membre, Epargnant::type($g->gerant, 'EPC', Devise::CDF));
    actingAs($g->caissier);

    post("/comptes-epargne/{$usd->id}/conversions", ['montant' => '10', 'destination_id' => $cdf->id, 'cle' => (string) Str::uuid()])
        ->assertSessionHasErrors('montant');
});

it('ajoute « Taux de change » au menu', function (string $role, bool $visible) {
    actingAs(Acteurs::avecRole($role));

    expect(str_contains((string) get('/tableau-de-bord')->getContent(), 'href="'.url('/change').'"'))->toBe($visible);
})->with([['gerant', true], ['comptable', true], ['caissier', true], ['collecteur', false]]);
