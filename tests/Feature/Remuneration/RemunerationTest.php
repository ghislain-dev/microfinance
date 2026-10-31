<?php

declare(strict_types=1);

use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\Remuneration\Actions\CalculerInterets;
use App\Domain\Epargne\Remuneration\Actions\CalculerRistourne;
use App\Domain\Epargne\Remuneration\Actions\DeciderRemuneration;
use App\Domain\Epargne\Remuneration\Models\Remuneration;
use App\Domain\Epargne\Remuneration\RemunerationRefusee;
use App\Domain\Epargne\Remuneration\StatutRemuneration;
use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\Exceptions\SoldeInsuffisant;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Actions\ChangerStatutMembre;
use App\Domain\Membres\StatutMembre;
use App\Domain\Organisation\Models\Organisation;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Epargnant;
use Tests\Support\GrandLivre;
use Tests\Support\Guichet;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

function usdRem(string $m): Montant
{
    return Montant::depuisSaisie($m, Devise::USD);
}

/**
 * Crédite un compte d'épargne à une date de valeur donnée (contrepartie : un produit).
 */
function crediter(Guichet $g, CompteEpargne $compte, string $montant, string $dateValeur): void
{
    actingAs($g->gerant);
    $m = usdRem($montant);
    app(MoteurFinancier::class)->executer((string) Str::uuid(), 'essai',
        [LigneEcriture::debit(GrandLivre::compte($g->gerant, NatureCompte::Produit), $m), LigneEcriture::credit($compte->compte, $m)],
        dateValeur: Carbon::parse($dateValeur));
}

function soldeRem(CompteEpargne $c): string
{
    return app(MoteurFinancier::class)->soldeReel($c->compte)->formate();
}

/**
 * Le 15 mars 2026 : produit à 6 % l'an, deux membres.
 *
 * @return array{Guichet, CompteEpargne, CompteEpargne}
 */
function epargnantsRemuneres(): array
{
    travelTo(Carbon::parse('2026-03-15 10:00'));
    $g = Guichet::creer();
    $type = Epargnant::type($g->gerant, 'EPV', Devise::USD, ['taux_interet_annuel' => '6']);
    $a = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), $type);
    $b = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), $type);

    return [$g, $a, $b];
}

it('enregistre le taux d’intérêt annuel du produit en points de base', function () {
    $g = Guichet::creer();

    $type = Epargnant::type($g->gerant, 'EPV', Devise::USD, ['taux_interet_annuel' => '3,25']);

    expect($type->taux_interet_pb)->toBe(325)->and($type->tauxInteretFormate())->toBe('3,25 %');
    expect(fn () => Epargnant::type($g->gerant, 'EPX', Devise::USD, ['taux_interet_annuel' => '1,234']))->toThrow(InvalidArgumentException::class);
});

it('calcule les intérêts du mois sur le solde de fin de mois, arrondis au centime inférieur', function () {
    [$g, $a, $b] = epargnantsRemuneres();
    crediter($g, $a, '1000', '2026-02-10');
    crediter($g, $b, '333,33', '2026-02-27');
    crediter($g, $b, '500', '2026-03-02'); // après la fin de février : ignoré

    $lot = app(CalculerInterets::class)->executer($g->organisation, '2026-02', Devise::USD);

    // 1000 × 6 % / 12 = 5,00 ; 333,33 × 0,5 % = 1,66665 → 1,66
    expect($lot->statut)->toBe(StatutRemuneration::Brouillon)
        ->and($lot->lignes()->orderBy('compte_epargne_id')->get()->map(fn ($l) => [$l->base->formate(), $l->montant->formate()])->all())
        ->toBe([['1 000,00 USD', '5,00 USD'], ['333,33 USD', '1,66 USD']])
        ->and($lot->montant_total->formate())->toBe('6,66 USD')
        ->and(soldeRem($a))->toBe('1 000,00 USD'); // rien n'est versé avant validation
});

it('exclut les membres non actifs, les soldes nuls et les produits sans taux', function () {
    [$g, $a, $b] = epargnantsRemuneres();
    $sansTaux = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), Epargnant::type($g->gerant, 'ZER'));
    crediter($g, $a, '100', '2026-02-01');
    crediter($g, $sansTaux, '100', '2026-02-01');
    app(ChangerStatutMembre::class)->executer($g->gerant, $a->membre, StatutMembre::Exclu);

    app(CalculerInterets::class)->executer($g->organisation, '2026-02', Devise::USD);
})->throws(RemunerationRefusee::class);

it('verse les intérêts à la validation : charge, épargne, mouvement et reçu', function () {
    [$g, $a] = epargnantsRemuneres();
    crediter($g, $a, '1000', '2026-02-10');
    $lot = app(CalculerInterets::class)->executer($g->organisation, '2026-02', Devise::USD);

    app(DeciderRemuneration::class)->valider(Acteurs::avecRole('comptable', $g->organisation), $lot, 'Contrôlé');

    expect($lot->fresh()?->statut)->toBe(StatutRemuneration::Validee)
        ->and(soldeRem($a))->toBe('1 005,00 USD')
        ->and(MouvementEpargne::where('type', 'interets')->sole()->montant->formate())->toBe('5,00 USD')
        ->and(Compte::where('numero', 'CHARGE-INTERETS-EPARGNE-USD')->sole()->solde_cache->formate())->toBe('5,00 USD');
});

it('ne calcule qu’un lot par mois, sauf après rejet', function () {
    [$g, $a] = epargnantsRemuneres();
    crediter($g, $a, '1000', '2026-02-10');
    $lot = app(CalculerInterets::class)->executer($g->organisation, '2026-02', Devise::USD);

    expect(fn () => app(CalculerInterets::class)->executer($g->organisation, '2026-02', Devise::USD))->toThrow(DomainException::class);
    app(DeciderRemuneration::class)->rejeter(Acteurs::avecRole('comptable', $g->organisation), $lot, 'Taux à revoir');
    expect(app(CalculerInterets::class)->executer($g->organisation, '2026-02', Devise::USD)->exists)->toBeTrue();
});

it('refuse de calculer un mois qui n’est pas terminé', function () {
    [$g] = epargnantsRemuneres();

    app(CalculerInterets::class)->executer($g->organisation, '2026-03', Devise::USD);
})->throws(InvalidArgumentException::class);

it('planifie le calcul le 1er de chaque mois et traite chaque organisation', function () {
    [$g, $a] = epargnantsRemuneres();
    crediter($g, $a, '1000', '2026-02-10');
    Auth::forgetUser();

    artisan('epargne:calculer-interets')->assertSuccessful();

    expect(Remuneration::withoutGlobalScopes()->where('periode', '2026-02')->count())->toBe(1);
    $evenement = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'epargne:calculer-interets'));
    expect($evenement?->expression)->toBe('0 3 1 * *');
});

/**
 * Alimente le compte « excédents à répartir » (en Phase 6 : affectation du résultat).
 */
function excedents(Guichet $g, string $montant): void
{
    actingAs($g->gerant);
    $excedents = app(OuvrirCompte::class)->obtenir('EXCEDENTS-A-REPARTIR-USD', 'Excédents à répartir (USD)', NatureCompte::FondsPropres, Devise::USD);
    $m = usdRem($montant);
    app(MoteurFinancier::class)->executer((string) Str::uuid(), 'essai', [LigneEcriture::debit(GrandLivre::compte($g->gerant, NatureCompte::Charge), $m), LigneEcriture::credit($excedents, $m)]);
}

it('répartit la ristourne au prorata, au centime exact (plus fort reste), membres actifs seulement', function () {
    [$g, $a, $b] = epargnantsRemuneres();
    $c = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), $a->type);
    $sorti = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), $a->type);
    foreach ([[$a, '100'], [$b, '100'], [$c, '100'], [$sorti, '900']] as [$compte, $m]) {
        crediter($g, $compte, $m, '2026-03-01');
    }
    app(ChangerStatutMembre::class)->executer($g->gerant, $sorti->membre, StatutMembre::Demissionnaire);

    $lot = app(CalculerRistourne::class)->executer($g->gerant, usdRem('10'), Carbon::parse('2026-03-10'));

    // 10,00 / 3 = 3,333… : deux comptes à 3,33, un à 3,34 ; total exact.
    $montants = $lot->lignes()->get()->map(fn ($l) => $l->montant->centimes)->sort()->values()->all();
    expect($montants)->toBe([333, 333, 334])
        ->and(array_sum($montants))->toBe(1000)
        ->and($lot->cree_par_id)->toBe($g->gerant->id);
});

it('fait valider la ristourne par une autre personne, prélevée sur les excédents à répartir', function () {
    [$g, $a, $b] = epargnantsRemuneres();
    crediter($g, $a, '300', '2026-03-01');
    crediter($g, $b, '100', '2026-03-01');
    $lot = app(CalculerRistourne::class)->executer($g->gerant, usdRem('40'), Carbon::parse('2026-03-10'));

    expect(fn () => app(DeciderRemuneration::class)->valider($g->gerant, $lot, 'moi'))->toThrow(AuthorizationException::class);

    $comptable = Acteurs::avecRole('comptable', $g->organisation);
    expect(fn () => app(DeciderRemuneration::class)->valider($comptable, $lot, 'Fonds absents'))->toThrow(SoldeInsuffisant::class)
        ->and($lot->fresh()?->statut)->toBe(StatutRemuneration::Brouillon);

    excedents($g, '40');
    app(DeciderRemuneration::class)->valider($comptable, $lot, 'Décision AG du 05/03');

    expect(soldeRem($a))->toBe('330,00 USD')->and(soldeRem($b))->toBe('110,00 USD')
        ->and(MouvementEpargne::where('type', 'ristourne')->count())->toBe(2);
});

it('refuse une ristourne sans le droit, nulle ou datée dans le futur', function () {
    [$g] = epargnantsRemuneres();

    expect(fn () => app(CalculerRistourne::class)->executer($g->caissier, usdRem('10'), Carbon::parse('2026-03-10')))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CalculerRistourne::class)->executer($g->gerant, usdRem('0'), Carbon::parse('2026-03-10')))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(CalculerRistourne::class)->executer($g->gerant, usdRem('10'), Carbon::parse('2026-03-20')))->toThrow(InvalidArgumentException::class);
});

it('n’affecte que l’organisation concernée', function () {
    [$g, $a] = epargnantsRemuneres();
    crediter($g, $a, '1000', '2026-02-10');
    Auth::forgetUser();
    $autre = Organisation::factory()->create();

    app(CalculerInterets::class)->executer($autre, '2026-02', Devise::USD);
})->throws(RemunerationRefusee::class);
