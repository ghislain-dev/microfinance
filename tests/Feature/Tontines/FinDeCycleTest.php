<?php

declare(strict_types=1);

use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Actions\CalculerPartage;
use App\Domain\Tontines\Actions\DeciderAide;
use App\Domain\Tontines\Actions\DeciderPartage;
use App\Domain\Tontines\Actions\DemanderAide;
use App\Domain\Tontines\Actions\DemarrerCycle;
use App\Domain\Tontines\Actions\SaisirSeance;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Partage;
use App\Domain\Tontines\Services\Dettes;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\TontineRefusee;
use App\Domain\Tontines\TypeGroupe;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\Acteurs;
use Tests\Support\Guichet;
use Tests\Support\Tontine;

function cdf(string $m): Montant
{
    return Montant::depuisSaisie($m, Devise::CDF);
}

function soldeCompte(Compte $compte): string
{
    return app(MoteurFinancier::class)->soldeReel($compte)->formate();
}

/**
 * Tient toutes les séances d'un cycle avec la même feuille.
 *
 * @param  array<int, array<string, mixed>>  $feuille
 */
function tenirToutes(Guichet $g, Cycle $cycle, array $feuille): void
{
    for ($rang = 1; $rang <= (int) $cycle->nombre_seances; $rang++) {
        app(SaisirSeance::class)->executer($g->caissier, seance($cycle, $rang), $feuille);
    }
}

it('verse une aide du fonds social demandée par le gérant et validée par le comité', function () {
    [$g, $cycle, $membres, $comptes] = cycleEnCours();
    app(SaisirSeance::class)->executer($g->caissier, seance($cycle, 1), feuille($membres)); // FS : 1 500
    $comite = Acteurs::avecRole('comite', $g->organisation);

    $aide = app(DemanderAide::class)->executer($g->gerant, $cycle, $membres[0], cdf('1000'), 'Deuil');
    expect(fn () => app(DeciderAide::class)->valider($g->gerant, $aide, 'moi'))->toThrow(AuthorizationException::class);
    app(DeciderAide::class)->valider($comite, $aide, 'Accordé');

    expect(soldeCompte($cycle->compteFondsSocial))->toBe('500,00 CDF')
        ->and(MouvementEpargne::where('type', 'aide_fonds_social')->sole()->montant->formate())->toBe('1 000,00 CDF');

    $tropGrosse = app(DemanderAide::class)->executer($g->gerant, $cycle, $membres[1], cdf('600'), 'Maladie');
    expect(fn () => app(DeciderAide::class)->valider($comite, $tropGrosse, 'Accordé'))->toThrow(TontineRefusee::class, 'fonds social');
});

it('partage un cycle AVEC au prorata des parts, dettes déduites, validé par le comité et clôturé', function () {
    [$g, $cycle, $membres, $comptes] = cycleEnCours(TypeGroupe::Avec, ['nombre_seances' => 2, 'penalite_impaye' => '700']);
    [$a, $b, $c] = $membres;
    // a : 3 parts × 2 ; b : 1 part × 2 ; c : 0 part (pénalité 700 × 2, impayée)
    tenirToutes($g, $cycle, feuille($membres, [$a->id => ['parts' => 3], $b->id => ['parts' => 1], $c->id => ['parts' => 0]], cotisation: '0'));
    // Fonds : 8 parts × 1 000 + pénalités 1 400 = 9 400 ; parts : a 6, b 2, c 0.

    $partage = app(CalculerPartage::class)->executer($g->gerant, $cycle);
    $lignes = $partage->lignes()->get()->keyBy('membre_id');
    expect($partage->montant_total->formate())->toBe('9 400,00 CDF')
        ->and($lignes[$a->id]->part->formate())->toBe('7 050,00 CDF')   // 9 400 × 6/8
        ->and($lignes[$b->id]->part->formate())->toBe('2 350,00 CDF')
        ->and(isset($lignes[$c->id]) ? $lignes[$c->id]->part->centimes : 0)->toBe(0);

    expect(fn () => app(DeciderPartage::class)->valider($g->gerant, $partage, 'moi'))->toThrow(AuthorizationException::class);
    app(DeciderPartage::class)->valider(Acteurs::avecRole('comite', $g->organisation), $partage, 'AG du 30/11');

    expect($cycle->fresh()?->statut)->toBe(StatutCycle::Cloture)
        ->and(soldeCompte($cycle->compteFonds))->toBe('0,00 CDF')
        ->and(soldeCdf($comptes[$a->id]))->toBe('7 050,00 CDF')
        ->and(app(Dettes::class)->du($cycle, $c)->formate())->toBe('1 400,00 CDF'); // aucune part : la dette reste due
});

it('redistribue à parts égales en rotative et règle les dettes sur la part, au centime exact', function () {
    [$g, $cycle, $membres, $comptes] = cycleEnCours();
    [$a, $b, $c] = $membres;
    tenirToutes($g, $cycle, feuille($membres, [$a->id => ['presence' => 'retard'], $b->id => ['presence' => 'absent']]));
    // Pénalités au fonds : (500 + 1 000) × 3 séances = 4 500 → 1 500 chacun.
    $avant = [$a->id => soldeCdf($comptes[$a->id]), $c->id => soldeCdf($comptes[$c->id])];

    $partage = app(CalculerPartage::class)->executer($g->gerant, $cycle);
    $lignes = $partage->lignes()->get()->keyBy('membre_id');
    expect($lignes->sum(fn ($l) => $l->part->centimes))->toBe(450000)
        ->and($lignes[$a->id]->compensation->formate())->toBe('1 500,00 CDF') // dette de retards : 1 500
        ->and($lignes[$a->id]->verse->formate())->toBe('0,00 CDF')
        ->and($lignes[$b->id]->compensation->formate())->toBe('1 500,00 CDF') // dette 3 000, part 1 500
        ->and($lignes[$c->id]->verse->formate())->toBe('1 500,00 CDF');

    app(DeciderPartage::class)->valider(Acteurs::avecRole('comite', $g->organisation), $partage, 'Fin de cycle');

    expect(app(Dettes::class)->du($cycle, $a)->formate())->toBe('0,00 CDF')
        ->and(app(Dettes::class)->du($cycle, $b)->formate())->toBe('1 500,00 CDF')
        ->and(soldeCdf($comptes[$c->id]))->not->toBe($avant[$c->id]);
});

it('refuse le partage avant la dernière séance, ou un second lot', function () {
    [$g, $cycle, $membres] = cycleEnCours();
    app(SaisirSeance::class)->executer($g->caissier, seance($cycle, 1), feuille($membres));

    expect(fn () => app(CalculerPartage::class)->executer($g->gerant, $cycle))->toThrow(TontineRefusee::class);
    tenirToutesRestantes($g, $cycle, $membres);
    app(CalculerPartage::class)->executer($g->gerant, $cycle);
    expect(fn () => app(CalculerPartage::class)->executer($g->gerant, $cycle))->toThrow(TontineRefusee::class);
});

/**
 * @param  list<Membre>  $membres
 */
function tenirToutesRestantes(Guichet $g, Cycle $cycle, array $membres): void
{
    for ($rang = 2; $rang <= (int) $cycle->nombre_seances; $rang++) {
        app(SaisirSeance::class)->executer($g->caissier, seance($cycle, $rang), feuille($membres));
    }
}

it('reporte le fonds social au cycle suivant à son démarrage', function () {
    [$g, $cycle, $membres] = cycleEnCours();
    tenirToutes($g, $cycle, feuille($membres)); // FS : 500 × 3 × 3 = 4 500
    $partage = app(CalculerPartage::class)->executer($g->gerant, $cycle);
    app(DeciderPartage::class)->valider(Acteurs::avecRole('comite', $g->organisation), $partage, 'Fin');

    $suivant = Tontine::cycle($g, $cycle->groupe);
    app(DemarrerCycle::class)->executer($g->gerant, $suivant);

    expect(soldeCompte($cycle->compteFondsSocial))->toBe('0,00 CDF')
        ->and(soldeCompte(($suivant->fresh() ?? $suivant)->compteFondsSocial))->toBe('4 500,00 CDF');
});

it('rejette un partage pour le recalculer', function () {
    [$g, $cycle, $membres] = cycleEnCours();
    tenirToutes($g, $cycle, feuille($membres));
    $partage = app(CalculerPartage::class)->executer($g->gerant, $cycle);

    app(DeciderPartage::class)->rejeter(Acteurs::avecRole('comite', $g->organisation), $partage, 'À revoir');

    expect(Partage::count())->toBe(1)
        ->and(app(CalculerPartage::class)->executer($g->gerant, $cycle)->exists)->toBeTrue()
        ->and($cycle->fresh()?->statut)->toBe(StatutCycle::EnCours);
});
