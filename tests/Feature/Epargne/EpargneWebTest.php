<?php

declare(strict_types=1);

use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\TypeCompteEpargne;
use App\Domain\Membres\Actions\ChangerStatutMembre;
use App\Domain\Membres\StatutMembre;
use App\Support\Montant\Devise;
use Tests\Support\Acteurs;
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
function produit(array $autres = []): array
{
    return [
        'code' => 'EPV', 'libelle' => 'Épargne à vue', 'devise' => 'USD',
        'solde_minimum' => '5', 'depot_minimum' => '1', 'frais_retrait' => '0,25', 'duree_blocage_mois' => '',
        ...$autres,
    ];
}

it('laisse le gérant créer un produit d’épargne depuis le formulaire', function () {
    $g = Guichet::creer();
    actingAs($g->gerant);

    get('/epargne/types/creer')->assertOk();
    post('/epargne/types', produit())->assertRedirect('/epargne/types');

    $type = TypeCompteEpargne::sole();
    expect($type->frais_retrait->formate())->toBe('0,25 USD')->and($type->duree_blocage_mois)->toBeNull();
    get('/epargne/types')->assertOk()->assertSeeText('EPV')->assertSeeText('5,00 USD');
});

it('valide le produit (code, montants, durée)', function () {
    $g = Guichet::creer();
    actingAs($g->gerant);

    post('/epargne/types', produit(['code' => 'e p', 'solde_minimum' => '1,234', 'depot_minimum' => '-1', 'duree_blocage_mois' => '0', 'devise' => 'EUR']))
        ->assertSessionHasErrors(['code', 'solde_minimum', 'depot_minimum', 'duree_blocage_mois', 'devise']);
    expect(TypeCompteEpargne::count())->toBe(0);
});

it('modifie un produit sans changer sa devise', function () {
    $g = Guichet::creer();
    actingAs($g->gerant);
    $type = Epargnant::type($g->gerant);

    get("/epargne/types/{$type->id}/modifier")->assertOk()->assertSee('value="Épargne EPV"', false);
    put("/epargne/types/{$type->id}", produit(['libelle' => 'Épargne libre', 'devise' => 'CDF', 'actif' => '1']))->assertRedirect('/epargne/types');

    expect($type->fresh()?->libelle)->toBe('Épargne libre')->and($type->fresh()?->devise->value)->toBe('USD');
});

it('refuse le paramétrage au caissier', function () {
    $g = Guichet::creer();
    actingAs($g->caissier);

    get('/epargne/types')->assertForbidden();
    post('/epargne/types', produit())->assertForbidden();
});

it('ouvre un compte depuis la fiche membre et l’affiche avec son solde', function () {
    $g = Guichet::creer();
    $membre = Epargnant::membre($g);
    $type = Epargnant::type($g->gerant, 'DAT', Devise::CDF, ['duree_blocage_mois' => 12]);
    actingAs($g->caissier);

    get("/membres/{$membre->id}")->assertOk()->assertSee('name="type_compte_epargne_id"', false);
    post("/membres/{$membre->id}/comptes-epargne", ['type_compte_epargne_id' => $type->id])->assertRedirect("/membres/{$membre->id}");

    $compte = CompteEpargne::sole();
    get("/membres/{$membre->id}")->assertOk()
        ->assertSeeText($compte->numero)
        ->assertSeeText('0,00 CDF')
        ->assertSeeText('Bloqué jusqu’au '.today()->addMonthsNoOverflow(12)->format('d/m/Y'));
});

it('montre les comptes au collecteur sans lui permettre d’en ouvrir', function () {
    $g = Guichet::creer();
    $membre = Epargnant::membre($g);
    $type = Epargnant::type($g->gerant);
    actingAs($g->caissier);
    post("/membres/{$membre->id}/comptes-epargne", ['type_compte_epargne_id' => $type->id]);
    actingAs(Acteurs::avecRole('collecteur', $g->organisation));

    get("/membres/{$membre->id}")->assertOk()->assertSeeText("{$membre->numero}-EPV-1")->assertDontSee('name="type_compte_epargne_id"', false);
    post("/membres/{$membre->id}/comptes-epargne", ['type_compte_epargne_id' => $type->id])->assertForbidden();
});

it('refuse un type inconnu ou d’une autre organisation, et un membre suspendu', function () {
    $g = Guichet::creer();
    $membre = Epargnant::membre($g);
    actingAs($g->caissier);

    post("/membres/{$membre->id}/comptes-epargne", ['type_compte_epargne_id' => 999999])->assertSessionHasErrors('type_compte_epargne_id');

    $type = Epargnant::type($g->gerant);
    app(ChangerStatutMembre::class)->executer($g->gerant, $membre, StatutMembre::Suspendu);
    actingAs($g->caissier);
    post("/membres/{$membre->id}/comptes-epargne", ['type_compte_epargne_id' => $type->id])->assertSessionHasErrors('type_compte_epargne_id');
    expect(CompteEpargne::count())->toBe(0);
});

it('ajoute « Produits d’épargne » au menu de l’admin et du gérant', function (string $role, bool $visible) {
    actingAs(Acteurs::avecRole($role));

    expect(str_contains((string) get('/tableau-de-bord')->getContent(), 'href="'.url('/epargne/types').'"'))->toBe($visible);
})->with([['admin', true], ['gerant', true], ['caissier', false], ['comptable', false]]);
