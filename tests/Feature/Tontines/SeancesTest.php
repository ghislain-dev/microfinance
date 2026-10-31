<?php

declare(strict_types=1);

use App\Domain\Caisse\Actions\OuvrirSession;
use App\Domain\Caisse\Billetage;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\Models\Ecriture;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Actions\DemarrerCycle;
use App\Domain\Tontines\Actions\SaisirSeance;
use App\Domain\Tontines\Models\Cotisation;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Seance;
use App\Domain\Tontines\Models\Tour;
use App\Domain\Tontines\Services\Dettes;
use App\Domain\Tontines\StatutSeance;
use App\Domain\Tontines\TontineRefusee;
use App\Domain\Tontines\TypeGroupe;
use App\Support\Audit\LigneAudit;
use App\Support\Montant\Devise;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Acteurs;
use Tests\Support\Epargnant;
use Tests\Support\Guichet;
use Tests\Support\Tontine;

use function Pest\Laravel\actingAs;

/**
 * Cycle rotatif de 3 membres (10 000 CDF, FS 500, pénalités 1 000 / 500 / 2 000), démarré,
 * chaque membre avec un compte d'épargne CDF ; caisse ouverte par le caissier.
 *
 * @param  array<string, mixed>  $parametres
 * @param  list<int>|null  $ordre
 * @return array{Guichet, Cycle, list<Membre>, array<int, CompteEpargne>}
 */
function cycleEnCours(TypeGroupe $type = TypeGroupe::Rotative, array $parametres = [], ?array $ordre = null): array
{
    $g = Guichet::creer();
    [$groupe, $membres] = Tontine::groupe($g, $type, 3);
    $typeEpargne = Epargnant::type($g->gerant, 'EPC', Devise::CDF);
    $comptes = [];
    foreach ($membres as $m) {
        $comptes[$m->id] = app(OuvrirCompteEpargne::class)->executer($g->gerant, $m, $typeEpargne);
    }
    $cycle = Tontine::cycle($g, $groupe, $type === TypeGroupe::Avec ? ['nombre_seances' => 4, 'mode_tours' => null, 'montant_cotisation' => '1000', ...$parametres] : $parametres);
    app(DemarrerCycle::class)->executer($g->gerant, $cycle, $ordre === null ? null : array_map(fn (int $i) => $membres[$i]->id, $ordre));
    actingAs($g->caissier);
    app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());

    return [$g, $cycle->fresh() ?? $cycle, $membres, $comptes];
}

/**
 * Tout le monde présent et à jour.
 *
 * @param  list<Membre>  $membres
 * @param  array<int, array<string, mixed>>  $exceptions  membre_id => champs
 * @return array<int, array<string, mixed>>
 */
function feuille(array $membres, array $exceptions = [], string $cotisation = '10000'): array
{
    $lignes = [];
    foreach ($membres as $m) {
        $lignes[$m->id] = ['presence' => 'present', 'cotisation' => $cotisation, 'parts' => null, 'fonds_social' => '500', 'reglement' => '0', ...($exceptions[$m->id] ?? [])];
    }

    return $lignes;
}

function seance(Cycle $cycle, int $rang): Seance
{
    return Seance::where('cycle_id', $cycle->id)->where('rang', $rang)->sole();
}

function soldeCdf(CompteEpargne $c): string
{
    return app(MoteurFinancier::class)->soldeReel($c->compte)->formate();
}

it('planifie les séances et tire l’ordre des tours au démarrage, sans le recalculer', function () {
    [, $cycle, $membres] = cycleEnCours();

    expect(Seance::where('cycle_id', $cycle->id)->orderBy('rang')->get()->map(fn (Seance $s) => $s->date_prevue->toDateString())->all())
        ->toBe([today()->addDay()->toDateString(), today()->addDays(8)->toDateString(), today()->addDays(15)->toDateString()]);
    $tours = Tour::where('cycle_id', $cycle->id)->orderBy('rang')->pluck('membre_id')->all();
    expect($tours)->toHaveCount(3)->and(collect($tours)->sort()->values()->all())->toBe(collect($membres)->pluck('id')->sort()->values()->all())
        ->and(LigneAudit::where('action', 'tontines.tirage')->sole()->apres)->toMatchArray(['ordre' => $tours])
        ->and(fn () => DB::statement('update tours set rang = 9'))->toThrow(QueryException::class, 'insertion seule');
});

it('respecte l’ordre fixé par le gérant', function () {
    [, $cycle, $membres] = cycleEnCours(TypeGroupe::Rotative, ['mode_tours' => 'ordre_fixe'], [2, 0, 1]);

    expect(Tour::where('cycle_id', $cycle->id)->orderBy('rang')->pluck('membre_id')->all())
        ->toBe([$membres[2]->id, $membres[0]->id, $membres[1]->id]);
});

it('saisit une séance complète : cotisations en caisse, fonds social, pot complet au bénéficiaire', function () {
    [$g, $cycle, $membres, $comptes] = cycleEnCours();
    $beneficiaire = Tour::where('cycle_id', $cycle->id)->where('rang', 1)->value('membre_id');

    app(SaisirSeance::class)->executer($g->caissier, seance($cycle, 1), feuille($membres));

    expect(seance($cycle, 1)->statut)->toBe(StatutSeance::Tenue)
        ->and(soldeCdf($comptes[$beneficiaire]))->toBe('30 000,00 CDF')
        ->and(app(MoteurFinancier::class)->soldeReel($g->caisse->compte(Devise::CDF))->formate())->toBe('31 500,00 CDF')
        ->and(app(MoteurFinancier::class)->soldeReel($cycle->compteFondsSocial)->formate())->toBe('1 500,00 CDF')
        ->and(app(MoteurFinancier::class)->soldeReel($cycle->compteFonds)->formate())->toBe('0,00 CDF');
    foreach (['CDF'] as $devise) {
        expect((int) Ecriture::where('devise', $devise)->sum('debit'))->toBe((int) Ecriture::where('devise', $devise)->sum('credit'));
    }
});

it('verse le pot complet malgré un impayé : dette + pénalités, puis règlement à une séance suivante', function () {
    [$g, $cycle, $membres, $comptes] = cycleEnCours();
    [$a, $b] = $membres;
    $beneficiaire = Tour::where('cycle_id', $cycle->id)->where('rang', 1)->value('membre_id');

    app(SaisirSeance::class)->executer($g->caissier, seance($cycle, 1), feuille($membres, [
        $a->id => ['presence' => 'absent', 'cotisation' => '4000', 'fonds_social' => '0'],
        $b->id => ['presence' => 'retard'],
    ]));

    // a : cotisation 6 000 + FS 500 + absence 1 000 + impayé 2 000 = 9 500 ; b : retard 500.
    expect(app(Dettes::class)->du($cycle, $a)->formate())->toBe('9 500,00 CDF')
        ->and(app(Dettes::class)->du($cycle, $b)->formate())->toBe('500,00 CDF')
        ->and(soldeCdf($comptes[$beneficiaire]))->toBe('30 000,00 CDF')
        ->and(app(MoteurFinancier::class)->soldeReel($cycle->compteFonds)->formate())->toBe('3 500,00 CDF'); // pénalités au cycle

    app(SaisirSeance::class)->executer($g->caissier, seance($cycle, 2), feuille($membres, [$a->id => ['reglement' => '9500']]));
    expect(app(Dettes::class)->du($cycle, $a)->formate())->toBe('0,00 CDF');
});

it('refuse un règlement supérieur à la dette, une coupure de cotisation au-delà du dû, un membre manquant', function () {
    [$g, $cycle, $membres] = cycleEnCours();
    $saisir = app(SaisirSeance::class);

    expect(fn () => $saisir->executer($g->caissier, seance($cycle, 1), feuille($membres, [$membres[0]->id => ['reglement' => '1']])))->toThrow(TontineRefusee::class)
        ->and(fn () => $saisir->executer($g->caissier, seance($cycle, 1), feuille($membres, [$membres[0]->id => ['cotisation' => '10000,01']])))->toThrow(TontineRefusee::class)
        ->and(fn () => $saisir->executer($g->caissier, seance($cycle, 1), array_slice(feuille($membres), 0, 2, true)))->toThrow(TontineRefusee::class)
        ->and(seance($cycle, 1)->statut)->toBe(StatutSeance::Prevue)
        ->and(Ecriture::count())->toBe(0);
});

it('tient les séances dans l’ordre, une seule fois chacune, avec une caisse ouverte', function () {
    [$g, $cycle, $membres] = cycleEnCours();
    $saisir = app(SaisirSeance::class);

    expect(fn () => $saisir->executer($g->caissier, seance($cycle, 2), feuille($membres)))->toThrow(TontineRefusee::class);
    $saisir->executer($g->caissier, seance($cycle, 1), feuille($membres));
    $saisir->executer($g->caissier, seance($cycle, 1), feuille($membres)); // double envoi : sans effet
    expect(Cotisation::where('type', 'cotisation')->count())->toBe(3);

    actingAs($g->gerant);
    expect(fn () => $saisir->executer($g->gerant, seance($cycle, 2), feuille($membres)))->toThrow(CompteEpargneRefuse::class)
        ->and(fn () => $saisir->executer(Acteurs::avecRole('collecteur', $g->organisation), seance($cycle, 2), feuille($membres)))->toThrow(AuthorizationException::class);
});

it('bloque en base une deuxième cotisation du même type pour un membre et une séance', function () {
    [$g, $cycle, $membres] = cycleEnCours();
    app(SaisirSeance::class)->executer($g->caissier, seance($cycle, 1), feuille($membres));

    $copie = (array) DB::table('cotisations_tontine')->where('type', 'cotisation')->first(['organisation_id', 'seance_id', 'membre_id', 'type', 'montant', 'operation_id']);
    DB::table('cotisations_tontine')->insert($copie);
})->throws(UniqueConstraintViolationException::class);

it('attribue le tour à l’enchère : plus forte remise, remise en produit de l’organisation', function () {
    [$g, $cycle, $membres, $comptes] = cycleEnCours(TypeGroupe::Rotative, ['mode_tours' => 'enchere']);
    expect(Tour::where('cycle_id', $cycle->id)->count())->toBe(0);

    app(SaisirSeance::class)->executer($g->caissier, seance($cycle, 1), feuille($membres), ['membre_id' => $membres[1]->id, 'remise' => '3000']);

    expect(soldeCdf($comptes[$membres[1]->id]))->toBe('27 000,00 CDF')
        ->and(Compte::where('numero', 'PRODUIT-ENCHERES-TONTINE-CDF')->sole()->solde_cache->formate())->toBe('3 000,00 CDF')
        ->and(Tour::where('cycle_id', $cycle->id)->sole()->membre_id)->toBe($membres[1]->id);
    expect(fn () => app(SaisirSeance::class)->executer($g->caissier, seance($cycle, 2), feuille($membres), ['membre_id' => $membres[1]->id, 'remise' => '0']))
        ->toThrow(TontineRefusee::class); // a déjà reçu son tour
});

it('AVEC : achat de 0 à 5 parts, pénalité sans dette si aucune part, pas de tour', function () {
    [$g, $cycle, $membres] = cycleEnCours(TypeGroupe::Avec);
    [$a, $b, $c] = $membres;

    app(SaisirSeance::class)->executer($g->caissier, seance($cycle, 1), feuille($membres, [
        $a->id => ['parts' => 5], $b->id => ['parts' => 1], $c->id => ['parts' => 0],
    ], cotisation: '0'));

    expect(app(MoteurFinancier::class)->soldeReel($cycle->compteFonds)->formate())->toBe('8 000,00 CDF') // 6 parts × 1 000 + pénalité 2 000
        ->and(app(Dettes::class)->du($cycle, $c)->formate())->toBe('2 000,00 CDF')
        ->and(Cotisation::where('type', 'parts')->where('membre_id', $a->id)->sole()->parts)->toBe(5)
        ->and(Tour::where('cycle_id', $cycle->id)->count())->toBe(0);
    expect(fn () => app(SaisirSeance::class)->executer($g->caissier, seance($cycle, 2), feuille($membres, [$a->id => ['parts' => 6], $b->id => ['parts' => 1], $c->id => ['parts' => 1]], cotisation: '0')))
        ->toThrow(TontineRefusee::class);
});

it('refuse de verser le tour à un bénéficiaire sans compte d’épargne dans la devise', function () {
    $g = Guichet::creer();
    [$groupe, $membres] = Tontine::groupe($g, TypeGroupe::Rotative, 2);
    $cycle = Tontine::cycle($g, $groupe, ['mode_tours' => 'ordre_fixe']);
    app(DemarrerCycle::class)->executer($g->gerant, $cycle, [$membres[0]->id, $membres[1]->id]);
    actingAs($g->caissier);
    app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());

    app(SaisirSeance::class)->executer($g->caissier, seance($cycle, 1), feuille($membres));
})->throws(TontineRefusee::class, 'compte d’épargne');
