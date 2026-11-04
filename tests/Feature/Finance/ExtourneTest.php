<?php

declare(strict_types=1);

use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\Exceptions\DejaExtournee;
use App\Domain\Finance\Exceptions\SoldeInsuffisant;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\Models\Operation;
use App\Domain\Finance\MoteurFinancier;
use App\Models\User;
use App\Support\Audit\LigneAudit;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Tests\Support\Acteurs;
use Tests\Support\GrandLivre;

use function Pest\Laravel\actingAs;

/**
 * Un caissier de l'organisation fait un dépôt de 100 USD.
 *
 * @return array{caissier: User, caisse: Compte, epargne: Compte, depot: Operation}
 */
function depotDeCaissier(): array
{
    $caissier = Acteurs::avecRole('caissier');
    $caisse = GrandLivre::compte($caissier, NatureCompte::Caisse);
    $epargne = GrandLivre::compte($caissier, NatureCompte::Epargne);
    actingAs($caissier);
    $cent = Montant::depuisSaisie('100', Devise::USD);
    $depot = app(MoteurFinancier::class)->executer('depot-1', 'depot', [LigneEcriture::debit($caisse, $cent), LigneEcriture::credit($epargne, $cent)]);

    return compact('caissier', 'caisse', 'epargne', 'depot');
}

function soldeDe(Compte $compte): int
{
    return Compte::withoutGlobalScopes()->findOrFail($compte->id)->solde_cache->centimes;
}

it('extourne une opération : écritures inversées, soldes revenus, lien, motif et validateur', function () {
    ['caissier' => $caissier, 'caisse' => $caisse, 'epargne' => $epargne, 'depot' => $depot] = depotDeCaissier();
    $gerant = Acteurs::avecRole('gerant', $caissier->organisation);
    actingAs($gerant);

    $extourne = app(MoteurFinancier::class)->extourner($depot, 'Erreur de membre à la saisie', $gerant);

    expect($extourne->extourne_de_id)->toBe($depot->id)
        ->and($extourne->type)->toBe('extourne')
        ->and($extourne->motif)->toBe('Erreur de membre à la saisie')
        ->and($extourne->valide_par_id)->toBe($gerant->id)
        ->and($extourne->ecritures()->orderBy('id')->get(['compte_id', 'debit', 'credit'])->toArray())
        ->toBe([
            ['compte_id' => $caisse->id, 'debit' => 0, 'credit' => 10000],
            ['compte_id' => $epargne->id, 'debit' => 10000, 'credit' => 0],
        ])
        ->and(soldeDe($caisse))->toBe(0)
        ->and(soldeDe($epargne))->toBe(0)
        ->and($depot->extourne()->first()?->id)->toBe($extourne->id);
});

it('accepte un comptable comme validateur', function () {
    ['caissier' => $caissier, 'depot' => $depot] = depotDeCaissier();
    $comptable = Acteurs::avecRole('comptable', $caissier->organisation);
    actingAs($comptable);

    expect(app(MoteurFinancier::class)->extourner($depot, 'Doublon', $comptable)->exists)->toBeTrue();
});

it('refuse que l’auteur de l’opération valide sa propre extourne, même gérant', function () {
    $gerant = Acteurs::avecRole('gerant');
    $caisse = GrandLivre::compte($gerant, NatureCompte::Caisse);
    $epargne = GrandLivre::compte($gerant, NatureCompte::Epargne);
    actingAs($gerant);
    $dix = Montant::depuisSaisie('10', Devise::USD);
    $depot = app(MoteurFinancier::class)->executer('d', 'depot', [LigneEcriture::debit($caisse, $dix), LigneEcriture::credit($epargne, $dix)]);

    app(MoteurFinancier::class)->extourner($depot, 'Je me corrige', $gerant);
})->throws(AuthorizationException::class);

it('refuse un validateur sans le droit (caissier, collecteur, admin)', function (string $role) {
    ['caissier' => $caissier, 'depot' => $depot] = depotDeCaissier();
    $autre = Acteurs::avecRole($role, $caissier->organisation);
    actingAs($autre);

    app(MoteurFinancier::class)->extourner($depot, 'Essai', $autre);
})->throws(AuthorizationException::class)->with(['caissier', 'collecteur', 'admin']);

it('refuse un validateur d’une autre organisation', function () {
    ['depot' => $depot] = depotDeCaissier();
    Auth::forgetUser();
    $gerantAilleurs = Acteurs::avecRole('gerant');
    actingAs($gerantAilleurs);

    app(MoteurFinancier::class)->extourner($depot, 'Essai', $gerantAilleurs);
})->throws(AuthorizationException::class);

it('n’extourne qu’une fois', function () {
    ['caissier' => $caissier, 'depot' => $depot] = depotDeCaissier();
    $gerant = Acteurs::avecRole('gerant', $caissier->organisation);
    actingAs($gerant);
    app(MoteurFinancier::class)->extourner($depot, 'Erreur', $gerant);

    expect(fn () => app(MoteurFinancier::class)->extourner($depot, 'Encore', $gerant))->toThrow(DejaExtournee::class)
        ->and(Operation::where('extourne_de_id', $depot->id)->count())->toBe(1);
});

it('exige un motif', function () {
    ['caissier' => $caissier, 'depot' => $depot] = depotDeCaissier();
    $gerant = Acteurs::avecRole('gerant', $caissier->organisation);
    actingAs($gerant);

    app(MoteurFinancier::class)->extourner($depot, '   ', $gerant);
})->throws(InvalidArgumentException::class);

it('refuse une extourne qui rendrait l’épargne négative (argent déjà retiré)', function () {
    ['caissier' => $caissier, 'caisse' => $caisse, 'epargne' => $epargne, 'depot' => $depot] = depotDeCaissier();
    $soixante = Montant::depuisSaisie('60', Devise::USD);
    app(MoteurFinancier::class)->executer('retrait', 'retrait', [LigneEcriture::debit($epargne, $soixante), LigneEcriture::credit($caisse, $soixante)]);
    $gerant = Acteurs::avecRole('gerant', $caissier->organisation);
    actingAs($gerant);

    app(MoteurFinancier::class)->extourner($depot, 'Erreur', $gerant);
})->throws(SoldeInsuffisant::class);

it('extourne aujourd’hui une opération d’une période désormais clôturée', function () {
    ['caissier' => $caissier, 'caisse' => $caisse, 'depot' => $depot] = depotDeCaissier();
    $hier = Montant::depuisSaisie('5', Devise::USD);
    $produit = GrandLivre::compte($caissier, NatureCompte::Produit);
    actingAs($caissier);
    $ancienne = app(MoteurFinancier::class)->executer('ancienne', 'frais', [LigneEcriture::debit($caisse, $hier), LigneEcriture::credit($produit, $hier)], dateValeur: today()->subDays(3));
    Auth::forgetUser();
    cloturerJusquAu($caissier->organisation()->firstOrFail(), today()->subDay());
    $gerant = Acteurs::avecRole('gerant', $caissier->organisation);
    actingAs($gerant);

    $extourne = app(MoteurFinancier::class)->extourner($ancienne, 'Frais prélevés à tort', $gerant);

    expect($extourne->date_valeur->toDateString())->toBe(today()->toDateString());
});

it('journalise l’extourne avec son motif', function () {
    ['caissier' => $caissier, 'depot' => $depot] = depotDeCaissier();
    $gerant = Acteurs::avecRole('gerant', $caissier->organisation);
    actingAs($gerant);

    app(MoteurFinancier::class)->extourner($depot, 'Erreur de saisie', $gerant);

    $ligne = LigneAudit::where('action', 'operations.extourne')->sole();
    expect($ligne->cible_id)->toBe($depot->id)
        ->and($ligne->user_id)->toBe($gerant->id)
        ->and($ligne->apres)->toMatchArray(['motif' => 'Erreur de saisie']);
});
