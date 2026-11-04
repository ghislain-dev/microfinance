<?php

declare(strict_types=1);

use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\Comptes\Sens;
use App\Domain\Finance\Events\OperationEnregistree;
use App\Domain\Finance\Exceptions\CompteIndisponible;
use App\Domain\Finance\Exceptions\OperationDesequilibree;
use App\Domain\Finance\Exceptions\PeriodeCloturee;
use App\Domain\Finance\Exceptions\SoldeInsuffisant;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\ClotureComptable;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\Models\Ecriture;
use App\Domain\Finance\Models\Operation;
use App\Domain\Finance\MoteurFinancier;
use App\Support\Audit\LigneAudit;
use App\Support\BaseDeDonnees\ModificationInterdite;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\DeuxOrganisations;
use Tests\Support\GrandLivre;

use function Pest\Laravel\actingAs;

function usd(string $saisie): Montant
{
    return Montant::depuisSaisie($saisie, Devise::USD);
}

function moteur(): MoteurFinancier
{
    return app(MoteurFinancier::class);
}

function solde(Compte $compte): string
{
    return Compte::withoutGlobalScopes()->findOrFail($compte->id)->solde_cache->formate();
}

/**
 * Dépôt d'épargne en espèces : débit caisse, crédit épargne du membre (skill §4).
 *
 * @return array{LigneEcriture, LigneEcriture}
 */
function depot(Compte $caisse, Compte $epargne, Montant $montant): array
{
    return [LigneEcriture::debit($caisse, $montant), LigneEcriture::credit($epargne, $montant)];
}

beforeEach(function () {
    $o = DeuxOrganisations::creer();
    app()->instance(DeuxOrganisations::class, $o);
    actingAs($o->userA);
});

function contexte(): DeuxOrganisations
{
    return app(DeuxOrganisations::class);
}

it('enregistre une opération équilibrée et met à jour les soldes dans le sens normal', function () {
    $caisse = GrandLivre::compte(contexte()->userA, NatureCompte::Caisse);
    $epargne = GrandLivre::compte(contexte()->userA, NatureCompte::Epargne);

    $operation = moteur()->executer((string) Str::uuid(), 'depot', depot($caisse, $epargne, usd('150,25')));

    expect($operation->ecritures()->count())->toBe(2)
        ->and($operation->auteur_id)->toBe(contexte()->userA->id)
        ->and($operation->organisation_id)->toBe(contexte()->orgA->id)
        ->and($operation->devise)->toBe(Devise::USD)
        ->and($operation->date_valeur->toDateString())->toBe(today()->toDateString())
        ->and(solde($caisse))->toBe('150,25 USD')
        ->and(solde($epargne))->toBe('150,25 USD')
        ->and((int) Ecriture::sum('debit'))->toBe((int) Ecriture::sum('credit'));
});

it('ne crée qu’une opération pour 10 envois identiques (idempotence)', function () {
    $caisse = GrandLivre::compte(contexte()->userA, NatureCompte::Caisse);
    $epargne = GrandLivre::compte(contexte()->userA, NatureCompte::Epargne);
    $cle = (string) Str::uuid();

    $ids = collect(range(1, 10))->map(fn () => moteur()->executer($cle, 'depot', depot($caisse, $epargne, usd('20')))->id);

    expect($ids->unique()->count())->toBe(1)
        ->and(Operation::where('idempotency_key', $cle)->count())->toBe(1)
        ->and(Ecriture::count())->toBe(2)
        ->and(solde($epargne))->toBe('20,00 USD');
});

it('garde la balance équilibrée sur un jeu d’opérations', function () {
    $caisse = GrandLivre::compte(contexte()->userA, NatureCompte::Caisse);
    $epargne = GrandLivre::compte(contexte()->userA, NatureCompte::Epargne);
    $produit = GrandLivre::compte(contexte()->userA, NatureCompte::Produit);

    moteur()->executer('o1', 'depot', depot($caisse, $epargne, usd('100')));
    moteur()->executer('o2', 'retrait', [LigneEcriture::debit($epargne, usd('30')), LigneEcriture::credit($caisse, usd('30'))]);
    moteur()->executer('o3', 'frais', [LigneEcriture::debit($epargne, usd('0,50')), LigneEcriture::credit($produit, usd('0,50'))]);

    expect((int) Ecriture::sum('debit'))->toBe((int) Ecriture::sum('credit'))
        ->and(solde($caisse))->toBe('70,00 USD')
        ->and(solde($epargne))->toBe('69,50 USD')
        ->and(solde($produit))->toBe('0,50 USD');
});

it('refuse une opération déséquilibrée ou mal formée, sans rien écrire', function (Closure $lignes) {
    $caisse = GrandLivre::compte(contexte()->userA, NatureCompte::Caisse);
    $epargne = GrandLivre::compte(contexte()->userA, NatureCompte::Epargne);

    expect(fn () => moteur()->executer('x', 'depot', $lignes($caisse, $epargne)))->toThrow(OperationDesequilibree::class)
        ->and(Operation::count())->toBe(0);
})->with([
    'débits ≠ crédits' => fn (Compte $c, Compte $e) => [LigneEcriture::debit($c, usd('10')), LigneEcriture::credit($e, usd('9,99'))],
    'une seule ligne' => fn (Compte $c, Compte $e) => [LigneEcriture::debit($c, usd('10'))],
    'aucune ligne' => fn (Compte $c, Compte $e) => [],
    'devises mélangées' => fn (Compte $c, Compte $e) => [LigneEcriture::debit($c, usd('10')), LigneEcriture::credit($e, Montant::depuisSaisie('10', Devise::CDF))],
]);

it('refuse une ligne nulle ou négative', function () {
    $caisse = GrandLivre::compte(contexte()->userA, NatureCompte::Caisse);

    LigneEcriture::debit($caisse, usd('0'));
})->throws(OperationDesequilibree::class);

it('refuse un compte d’une autre devise, inactif, inexistant ou d’une autre organisation', function (Closure $preparer) {
    $caisse = GrandLivre::compte(contexte()->userA, NatureCompte::Caisse);
    $autre = $preparer();
    actingAs(contexte()->userA);

    expect(fn () => moteur()->executer('x', 'depot', [LigneEcriture::debit($caisse, usd('5')), new LigneEcriture($autre, 0, 500, Devise::USD)]))
        ->toThrow(CompteIndisponible::class)
        ->and(Operation::count())->toBe(0);
})->with([
    'autre devise' => fn () => GrandLivre::compte(contexte()->userA, NatureCompte::Epargne, Devise::CDF)->id,
    'inactif' => fn () => tap(GrandLivre::compte(contexte()->userA, NatureCompte::Epargne), fn (Compte $c) => $c->update(['actif' => false]))->id,
    'inexistant' => fn () => 999_999,
    'autre organisation' => fn () => GrandLivre::compte(contexte()->userB, NatureCompte::Epargne)->id,
]);

it('refuse de faire passer une épargne, une caisse ou des parts sous zéro, sans rien écrire', function (NatureCompte $nature) {
    $compte = GrandLivre::compte(contexte()->userA, $nature);
    $contrepartie = GrandLivre::compte(contexte()->userA, NatureCompte::Charge);

    // Diminuer le compte : débit pour un compte au crédit, crédit pour un compte au débit.
    $lignes = $nature->sens() === Sens::Credit
        ? [LigneEcriture::debit($compte, usd('0,01')), LigneEcriture::credit($contrepartie, usd('0,01'))]
        : [LigneEcriture::credit($compte, usd('0,01')), LigneEcriture::debit($contrepartie, usd('0,01'))];

    expect(fn () => moteur()->executer('x', 'retrait', $lignes))->toThrow(SoldeInsuffisant::class)
        ->and(Operation::count())->toBe(0)
        ->and(solde($compte))->toBe('0,00 USD');
})->with([NatureCompte::Epargne, NatureCompte::Caisse, NatureCompte::PartsSociales]);

it('juge le découvert sur la somme des écritures, pas sur le cache', function () {
    $caisse = GrandLivre::compte(contexte()->userA, NatureCompte::Caisse);
    $epargne = GrandLivre::compte(contexte()->userA, NatureCompte::Epargne);
    moteur()->executer('d', 'depot', depot($caisse, $epargne, usd('10')));
    DB::table('comptes')->where('id', $epargne->id)->update(['solde_cache' => 1_000_000]); // cache faux

    moteur()->executer('r', 'retrait', [LigneEcriture::debit($epargne, usd('50')), LigneEcriture::credit($caisse, usd('50'))]);
})->throws(SoldeInsuffisant::class);

it('autorise un solde négatif sur les autres natures', function () {
    $pret = GrandLivre::compte(contexte()->userA, NatureCompte::Pret);
    $produit = GrandLivre::compte(contexte()->userA, NatureCompte::Produit);

    moteur()->executer('x', 'ajustement', [LigneEcriture::credit($pret, usd('5')), LigneEcriture::debit($produit, usd('5'))]);

    expect(solde($pret))->toBe('-5,00 USD')->and(solde($produit))->toBe('-5,00 USD');
});

it('refuse toute écriture dans une période clôturée (date de valeur ≤ clôture)', function () {
    $caisse = GrandLivre::compte(contexte()->userA, NatureCompte::Caisse);
    $epargne = GrandLivre::compte(contexte()->userA, NatureCompte::Epargne);
    cloturerJusquAu(contexte()->orgA, today()->subDays(10));
    actingAs(contexte()->userA);

    expect(fn () => moteur()->executer('a', 'depot', depot($caisse, $epargne, usd('1')), dateValeur: today()->subDays(10)))
        ->toThrow(PeriodeCloturee::class);

    $op = moteur()->executer('b', 'depot', depot($caisse, $epargne, usd('1')), dateValeur: today()->subDays(9));
    expect($op->date_valeur->toDateString())->toBe(today()->subDays(9)->toDateString())
        ->and($op->created_at->toDateString())->toBe(today()->toDateString()); // date de valeur ≠ date de saisie
});

it('clôture : jamais dans le futur, jamais en arrière, ligne non modifiable', function () {
    $organisation = contexte()->orgA;

    expect(fn () => cloturerJusquAu($organisation, today()->addDay()))->toThrow(InvalidArgumentException::class);
    cloturerJusquAu($organisation, today()->subMonth());
    expect(fn () => cloturerJusquAu($organisation, today()->subMonths(2)))->toThrow(InvalidArgumentException::class);

    expect(ClotureComptable::withoutGlobalScopes()->count())->toBe(1)
        ->and(fn () => ClotureComptable::withoutGlobalScopes()->firstOrFail()->delete())->toThrow(ModificationInterdite::class)
        ->and(fn () => DB::statement('delete from clotures_comptables'))->toThrow(QueryException::class, 'insertion seule');
});

it('n’affecte pas les autres organisations par une clôture', function () {
    Auth::forgetUser(); // en console, aucun utilisateur n'est connecté
    cloturerJusquAu(contexte()->orgB, today());
    actingAs(contexte()->userA);
    $caisse = GrandLivre::compte(contexte()->userA, NatureCompte::Caisse);
    $epargne = GrandLivre::compte(contexte()->userA, NatureCompte::Epargne);

    expect(moteur()->executer('a', 'depot', depot($caisse, $epargne, usd('1')))->exists)->toBeTrue();
});

it('émet OperationEnregistree après validation de la transaction, jamais en cas d’échec', function () {
    Event::fake([OperationEnregistree::class]);
    $caisse = GrandLivre::compte(contexte()->userA, NatureCompte::Caisse);
    $epargne = GrandLivre::compte(contexte()->userA, NatureCompte::Epargne);

    moteur()->executer('ok', 'depot', depot($caisse, $epargne, usd('1')));
    try {
        moteur()->executer('ko', 'retrait', [LigneEcriture::debit($epargne, usd('99')), LigneEcriture::credit($caisse, usd('99'))]);
    } catch (SoldeInsuffisant) {
    }

    Event::assertDispatchedTimes(OperationEnregistree::class, 1);
    expect((new ReflectionClass(OperationEnregistree::class))->implementsInterface(ShouldDispatchAfterCommit::class))->toBeTrue();
});

it('fonctionne sans utilisateur connecté (commande planifiée) : organisation des comptes', function () {
    $caisse = GrandLivre::compte(contexte()->userA, NatureCompte::Caisse);
    $epargne = GrandLivre::compte(contexte()->userA, NatureCompte::Epargne);
    Auth::forgetUser();
    app('auth')->forgetGuards();

    $op = moteur()->executer('nuit', 'depot', depot($caisse, $epargne, usd('1')));

    expect($op->organisation_id)->toBe(contexte()->orgA->id)->and($op->auteur_id)->toBeNull();
});

it('refuse de mélanger des comptes de deux organisations sans utilisateur connecté', function () {
    $caisseA = GrandLivre::compte(contexte()->userA, NatureCompte::Caisse);
    $epargneB = GrandLivre::compte(contexte()->userB, NatureCompte::Epargne);
    Auth::forgetUser();
    app('auth')->forgetGuards();

    moteur()->executer('x', 'depot', depot($caisseA, $epargneB, usd('1')));
})->throws(CompteIndisponible::class);

it('journalise chaque opération dans l’audit', function () {
    $caisse = GrandLivre::compte(contexte()->userA, NatureCompte::Caisse);
    $epargne = GrandLivre::compte(contexte()->userA, NatureCompte::Epargne);

    $op = moteur()->executer('audit', 'depot', depot($caisse, $epargne, usd('3')));

    $ligne = LigneAudit::where('action', 'operations.creation')->sole();
    expect($ligne->cible_id)->toBe($op->id)->and($ligne->apres)->toMatchArray(['type' => 'depot', 'cle' => 'audit']);
});
