<?php

declare(strict_types=1);

use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Epargne\Models\TypeCompteEpargne;
use App\Domain\Epargne\Remuneration\Models\Remuneration;
use App\Domain\Epargne\Remuneration\StatutRemuneration;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\MoteurFinancier;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Epargnant;
use Tests\Support\GrandLivre;
use Tests\Support\Guichet;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

/**
 * Le 15 mars 2026 : un compte à 6 % crédité de 1 000 USD en février.
 */
function remunerationPrete(): Guichet
{
    travelTo(Carbon::parse('2026-03-15 10:00'));
    $g = Guichet::creer();
    $compte = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g),
        Epargnant::type($g->gerant, 'EPV', Devise::USD, ['taux_interet_annuel' => '6']));
    actingAs($g->gerant);
    $m = Montant::depuisSaisie('1000', Devise::USD);
    app(MoteurFinancier::class)->executer((string) Str::uuid(), 'essai',
        [LigneEcriture::debit(GrandLivre::compte($g->gerant, NatureCompte::Produit), $m), LigneEcriture::credit($compte->compte, $m)],
        dateValeur: Carbon::parse('2026-02-10'));

    return $g;
}

it('saisit le taux d’intérêt dans le formulaire du produit', function () {
    $g = Guichet::creer();
    actingAs($g->gerant);

    post('/epargne/types', ['code' => 'EPV', 'libelle' => 'Épargne', 'devise' => 'USD', 'solde_minimum' => '0',
        'depot_minimum' => '0', 'frais_retrait' => '0', 'taux_interet_annuel' => '4,5'])->assertRedirect('/epargne/types');
    post('/epargne/types', ['code' => 'EPX', 'libelle' => 'Épargne', 'devise' => 'USD', 'solde_minimum' => '0',
        'depot_minimum' => '0', 'frais_retrait' => '0', 'taux_interet_annuel' => '4,555'])->assertSessionHasErrors('taux_interet_annuel');

    expect(TypeCompteEpargne::sole()->taux_interet_pb)->toBe(450);
    get('/epargne/types')->assertSeeText('4,50 %');
});

it('calcule les intérêts d’un mois puis les fait valider par le comptable', function () {
    $g = remunerationPrete();
    actingAs($g->gerant);

    get('/epargne/remunerations')->assertOk()->assertSee('name="periode"', false);
    post('/epargne/remunerations/interets', ['periode' => '2026-02', 'devise' => 'USD'])->assertRedirect();
    $lot = Remuneration::sole();
    get("/epargne/remunerations/{$lot->id}")->assertOk()->assertSeeText('5,00 USD')->assertDontSee('name="motif"', false);

    actingAs(Acteurs::avecRole('comptable', $g->organisation));
    get("/epargne/remunerations/{$lot->id}")->assertSee('name="motif"', false);
    post("/epargne/remunerations/{$lot->id}/valider", ['motif' => 'Contrôlé'])->assertRedirect("/epargne/remunerations/{$lot->id}");

    expect($lot->fresh()?->statut)->toBe(StatutRemuneration::Validee);
});

it('explique un calcul impossible (mois en cours, rien à verser, déjà calculé)', function () {
    $g = remunerationPrete();
    actingAs($g->gerant);

    post('/epargne/remunerations/interets', ['periode' => '2026-03', 'devise' => 'USD'])->assertSessionHasErrors('periode');
    post('/epargne/remunerations/interets', ['periode' => '2026-02', 'devise' => 'CDF'])->assertSessionHasErrors('periode');
    post('/epargne/remunerations/interets', ['periode' => '2026-02', 'devise' => 'USD']);
    post('/epargne/remunerations/interets', ['periode' => '2026-02', 'devise' => 'USD'])->assertSessionHasErrors('periode');
});

it('saisit une ristourne et explique l’absence de fonds à la validation', function () {
    $g = remunerationPrete();
    actingAs($g->gerant);

    get('/epargne/remunerations')->assertSeeText('Excédents à répartir');
    post('/epargne/remunerations/ristournes', ['montant' => '50', 'devise' => 'USD', 'date_reference' => '2026-03-01'])->assertRedirect();
    $lot = Remuneration::where('type', 'ristourne')->sole();

    actingAs(Acteurs::avecRole('comptable', $g->organisation));
    post("/epargne/remunerations/{$lot->id}/valider", ['motif' => 'AG'])->assertSessionHasErrors('motif');
    expect($lot->fresh()?->statut)->toBe(StatutRemuneration::Brouillon);

    post("/epargne/remunerations/{$lot->id}/rejeter", ['motif' => 'Fonds à affecter d’abord'])->assertRedirect();
    expect($lot->fresh()?->statut)->toBe(StatutRemuneration::Rejetee);
});

it('réserve les intérêts et ristournes au gérant et au comptable', function () {
    $g = remunerationPrete();
    actingAs($g->caissier);

    get('/epargne/remunerations')->assertForbidden();
    post('/epargne/remunerations/interets', ['periode' => '2026-02', 'devise' => 'USD'])->assertForbidden();
});

it('ajoute « Intérêts et ristournes » au menu', function (string $role, bool $visible) {
    actingAs(Acteurs::avecRole($role));

    expect(str_contains((string) get('/tableau-de-bord')->getContent(), 'href="'.url('/epargne/remunerations').'"'))->toBe($visible);
})->with([['gerant', true], ['comptable', true], ['admin', false], ['caissier', false]]);
