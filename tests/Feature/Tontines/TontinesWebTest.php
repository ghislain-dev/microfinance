<?php

declare(strict_types=1);

use App\Domain\Tontines\Models\Aide;
use App\Domain\Tontines\Models\Cotisation;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Groupe;
use App\Domain\Tontines\Models\Partage;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\StatutSeance;
use App\Domain\Tontines\TypeGroupe;
use Tests\Support\Acteurs;
use Tests\Support\Epargnant;
use Tests\Support\Guichet;
use Tests\Support\Tontine;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * @param  array<string, string>  $autres
 * @return array<string, string>
 */
function formulaireCycle(array $autres = []): array
{
    return [
        'devise' => 'CDF', 'periodicite' => 'hebdomadaire', 'date_debut' => today()->addDay()->toDateString(),
        'montant_cotisation' => '10000', 'cotisation_fonds_social' => '500', 'penalite_absence' => '1000',
        'penalite_retard' => '500', 'penalite_impaye' => '2000', 'mode_tours' => 'tirage', 'nombre_seances' => '',
        ...$autres,
    ];
}

it('crée un groupe, inscrit des membres et prépare un cycle depuis les écrans', function () {
    $g = Guichet::creer();
    $membres = [Epargnant::membre($g), Epargnant::membre($g)];
    actingAs($g->gerant);

    get('/tontines')->assertOk();
    post('/tontines', ['agence_id' => $g->agence->id, 'code' => 'MAMANS', 'nom' => 'Mamans du marché', 'type' => 'rotative'])->assertRedirect();
    $groupe = Groupe::sole();
    foreach ($membres as $m) {
        post("/tontines/{$groupe->id}/membres", ['numero' => $m->numero])->assertRedirect("/tontines/{$groupe->id}");
    }
    post("/tontines/{$groupe->id}/membres", ['numero' => 'INCONNU'])->assertSessionHasErrors('numero');
    post("/tontines/{$groupe->id}/cycles", formulaireCycle())->assertRedirect();

    $cycle = Cycle::sole();
    get("/tontines/{$groupe->id}")->assertOk()->assertSeeText($membres[0]->numero)->assertSeeText('Cycle 1');
    get("/cycles/{$cycle->id}")->assertOk()->assertSeeText('10 000,00 CDF')->assertSeeText('Tirage au sort');
});

it('valide le formulaire de cycle', function () {
    $g = Guichet::creer();
    [$groupe] = Tontine::groupe($g, TypeGroupe::Avec, 2);
    actingAs($g->gerant);

    post("/tontines/{$groupe->id}/cycles", formulaireCycle(['nombre_seances' => '', 'montant_cotisation' => 'abc', 'periodicite' => 'jamais']))
        ->assertSessionHasErrors(['montant_cotisation', 'periodicite']);
    post("/tontines/{$groupe->id}/cycles", formulaireCycle(['nombre_seances' => '']))->assertSessionHasErrors('nombre_seances');
    expect(Cycle::count())->toBe(0);
});

it('démarre un cycle à ordre fixé avec l’ordre saisi', function () {
    $g = Guichet::creer();
    [$groupe, $membres] = Tontine::groupe($g, TypeGroupe::Rotative, 2);
    $cycle = Tontine::cycle($g, $groupe, ['mode_tours' => 'ordre_fixe']);
    actingAs($g->gerant);

    get("/cycles/{$cycle->id}")->assertSee('name="ordre[1]"', false);
    post("/cycles/{$cycle->id}/demarrer", ['ordre' => [1 => $membres[1]->id, 2 => $membres[0]->id]])->assertRedirect("/cycles/{$cycle->id}");

    expect($cycle->fresh()?->statut)->toBe(StatutCycle::EnCours);
    get("/cycles/{$cycle->id}")->assertSeeText($membres[1]->numero);
});

it('saisit une feuille de séance au guichet', function () {
    [$g, $cycle, $membres] = cycleEnCours();
    $seance = seance($cycle, 1);

    get("/seances/{$seance->id}")->assertOk()->assertSee("name=\"lignes[{$membres[0]->id}][presence]\"", false);
    post("/seances/{$seance->id}", ['lignes' => feuille($membres)])->assertRedirect("/seances/{$seance->id}");

    // Les montants saisis arrivent bien jusqu'au moteur (pas seulement la présence).
    expect($seance->fresh()?->statut)->toBe(StatutSeance::Tenue)
        ->and((int) Cotisation::where('seance_id', $seance->id)->where('type', 'cotisation')->sum('montant'))->toBe(3_000_000)
        ->and((int) Cotisation::where('seance_id', $seance->id)->where('type', 'fonds_social')->sum('montant'))->toBe(150_000);
    get("/seances/{$seance->id}")->assertSeeText('10 500,00 CDF')->assertSeeText('Tenue')->assertDontSee("name=\"lignes[{$membres[0]->id}][presence]\"", false);
});

it('explique un refus de séance (règlement trop élevé)', function () {
    [, $cycle, $membres] = cycleEnCours();
    $seance = seance($cycle, 1);

    post("/seances/{$seance->id}", ['lignes' => feuille($membres, [$membres[0]->id => ['reglement' => '1']])])->assertSessionHasErrors('lignes');
    expect($seance->fresh()?->statut)->toBe(StatutSeance::Prevue);
});

it('demande une aide (gérant) puis la fait valider (comité)', function () {
    [$g, $cycle, $membres] = cycleEnCours();
    post('/seances/'.seance($cycle, 1)->id, ['lignes' => feuille($membres)]);
    actingAs($g->gerant);

    post("/cycles/{$cycle->id}/aides", ['membre_id' => $membres[0]->id, 'montant' => '1000', 'motif' => 'Deuil'])->assertRedirect("/cycles/{$cycle->id}");
    $aide = Aide::sole();

    actingAs(Acteurs::avecRole('comite', $g->organisation));
    get("/cycles/{$cycle->id}")->assertSeeText('Deuil');
    post("/aides/{$aide->id}/valider", ['motif' => 'Accordé'])->assertRedirect("/cycles/{$cycle->id}");
    expect($aide->fresh()?->getAttribute('statut'))->toBe('validee');
});

it('calcule le partage (gérant) et le fait valider (comité)', function () {
    [$g, $cycle, $membres] = cycleEnCours();
    for ($rang = 1; $rang <= 3; $rang++) {
        post('/seances/'.seance($cycle, $rang)->id, ['lignes' => feuille($membres, [$membres[0]->id => ['presence' => 'retard']])]);
    }
    actingAs($g->gerant);

    post("/cycles/{$cycle->id}/partage")->assertRedirect("/cycles/{$cycle->id}");
    $partage = Partage::sole();
    get("/cycles/{$cycle->id}")->assertSeeText('1 500,00 CDF');

    actingAs(Acteurs::avecRole('comite', $g->organisation));
    post("/partages/{$partage->id}/valider", ['motif' => 'AG'])->assertRedirect("/cycles/{$cycle->id}");
    expect($cycle->fresh()?->statut)->toBe(StatutCycle::Cloture);
});

it('réserve la gestion aux rôles prévus et isole les organisations', function () {
    $g = Guichet::creer();
    [$groupe] = Tontine::groupe($g);
    actingAs($g->caissier);

    get('/tontines')->assertOk()->assertDontSee('name="code"', false);
    post('/tontines', ['agence_id' => $g->agence->id, 'code' => 'X', 'nom' => 'X', 'type' => 'avec'])->assertForbidden();
    post("/tontines/{$groupe->id}/cycles", formulaireCycle())->assertForbidden();

    auth()->forgetUser();
    actingAs(Acteurs::avecRole('gerant'));
    get("/tontines/{$groupe->id}")->assertNotFound();
});

it('ajoute « Tontines » au menu du personnel', function () {
    actingAs(Acteurs::avecRole('collecteur'));

    expect(str_contains((string) get('/tableau-de-bord')->getContent(), 'href="'.url('/tontines').'"'))->toBeTrue();
});
