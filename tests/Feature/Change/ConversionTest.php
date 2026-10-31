<?php

declare(strict_types=1);

use App\Domain\Change\Actions\Convertir;
use App\Domain\Change\Actions\SaisirTaux;
use App\Domain\Change\Models\Conversion;
use App\Domain\Change\Models\TauxChange;
use App\Domain\Change\Taux;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\Models\Recu;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\Models\Ecriture;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Actions\ChangerStatutMembre;
use App\Domain\Membres\StatutMembre;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Epargnant;
use Tests\Support\GrandLivre;
use Tests\Support\Guichet;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travel;

/**
 * Membre avec un compte USD (100 USD) et un compte CDF (500 000 CDF), sans passer par la caisse.
 *
 * @return array{Guichet, CompteEpargne, CompteEpargne}
 */
function membreBiDevise(): array
{
    $g = Guichet::creer();
    $membre = Epargnant::membre($g);
    $usd = app(OuvrirCompteEpargne::class)->executer($g->gerant, $membre, Epargnant::type($g->gerant, 'EPU', Devise::USD));
    $cdf = app(OuvrirCompteEpargne::class)->executer($g->gerant, $membre, Epargnant::type($g->gerant, 'EPC', Devise::CDF));
    actingAs($g->gerant);
    foreach ([[$usd, '100'], [$cdf, '500000']] as [$compte, $saisie]) {
        $m = Montant::depuisSaisie($saisie, $compte->type->devise);
        $apport = GrandLivre::compte($g->gerant, NatureCompte::Produit, $m->devise);
        app(MoteurFinancier::class)->executer((string) Str::uuid(), 'essai', [LigneEcriture::debit($apport, $m), LigneEcriture::credit($compte->compte, $m)]);
    }
    app(SaisirTaux::class)->executer($g->gerant, Taux::depuisSaisie('2800'), Taux::depuisSaisie('2900'));
    actingAs($g->caissier);

    return [$g, $usd, $cdf];
}

function soldeChange(CompteEpargne $c): string
{
    return app(MoteurFinancier::class)->soldeReel($c->compte)->formate();
}

function produitChange(Devise $devise): string
{
    return Compte::where('numero', "PRODUIT-CHANGE-{$devise->value}")->sole()->solde_cache->formate();
}

it('convertit des USD en CDF au taux d’achat, marge en produit', function () {
    [$g, $usd, $cdf] = membreBiDevise();

    $conversion = app(Convertir::class)->executer($g->caissier, $usd, $cdf, Montant::depuisSaisie('10', Devise::USD), (string) Str::uuid());

    // 10 USD × 2 800 = 28 000 CDF au membre ; au taux moyen 2 850 → 28 500 ; marge 500 CDF.
    expect(soldeChange($usd))->toBe('90,00 USD')
        ->and(soldeChange($cdf))->toBe('528 000,00 CDF')
        ->and($conversion->montant_destination->formate())->toBe('28 000,00 CDF')
        ->and($conversion->marge->formate())->toBe('500,00 CDF')
        ->and(produitChange(Devise::CDF))->toBe('500,00 CDF')
        ->and($conversion->operation_source_id)->not->toBe($conversion->operation_destination_id);
});

it('convertit des CDF en USD au taux de vente, arrondi au centime inférieur', function () {
    [$g, $usd, $cdf] = membreBiDevise();

    $conversion = app(Convertir::class)->executer($g->caissier, $cdf, $usd, Montant::depuisSaisie('100000', Devise::CDF), (string) Str::uuid());

    // 100 000 / 2 900 = 34,4827… → 34,48 USD ; au taux moyen 2 850 : 35,0877… → 35,08 ; marge 0,60 USD.
    expect($conversion->montant_destination->formate())->toBe('34,48 USD')
        ->and(soldeChange($usd))->toBe('134,48 USD')
        ->and(soldeChange($cdf))->toBe('400 000,00 CDF')
        ->and($conversion->marge->formate())->toBe('0,60 USD')
        ->and(produitChange(Devise::USD))->toBe('0,60 USD');
});

it('équilibre chaque opération dans sa devise', function () {
    [$g, $usd, $cdf] = membreBiDevise();

    app(Convertir::class)->executer($g->caissier, $usd, $cdf, Montant::depuisSaisie('33,33', Devise::USD), (string) Str::uuid());

    foreach (['USD', 'CDF'] as $devise) {
        expect((int) Ecriture::where('devise', $devise)->sum('debit'))->toBe((int) Ecriture::where('devise', $devise)->sum('credit'));
    }
});

it('est idempotent', function () {
    [$g, $usd, $cdf] = membreBiDevise();
    $cle = (string) Str::uuid();

    app(Convertir::class)->executer($g->caissier, $usd, $cdf, Montant::depuisSaisie('10', Devise::USD), $cle);
    app(Convertir::class)->executer($g->caissier, $usd, $cdf, Montant::depuisSaisie('10', Devise::USD), $cle);

    expect(Conversion::count())->toBe(1)->and(soldeChange($usd))->toBe('90,00 USD');
});

it('utilise le taux le plus récent du jour et refuse sans taux du jour', function () {
    [$g, $usd, $cdf] = membreBiDevise();
    app(SaisirTaux::class)->executer($g->gerant, Taux::depuisSaisie('2700'), Taux::depuisSaisie('2750'));
    actingAs($g->caissier);

    $conversion = app(Convertir::class)->executer($g->caissier, $usd, $cdf, Montant::depuisSaisie('1', Devise::USD), (string) Str::uuid());
    expect($conversion->montant_destination->formate())->toBe('2 700,00 CDF');

    travel(1)->days();
    expect(fn () => app(Convertir::class)->executer($g->caissier, $usd, $cdf, Montant::depuisSaisie('1', Devise::USD), (string) Str::uuid()))
        ->toThrow(CompteEpargneRefuse::class, 'taux');
});

it('refuse un taux d’achat supérieur au taux de vente, et la saisie aux rôles sans le droit', function () {
    $g = Guichet::creer();

    expect(fn () => app(SaisirTaux::class)->executer($g->gerant, Taux::depuisSaisie('2900'), Taux::depuisSaisie('2800')))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(SaisirTaux::class)->executer($g->caissier, Taux::depuisSaisie('2800'), Taux::depuisSaisie('2900')))->toThrow(AuthorizationException::class);
    app(SaisirTaux::class)->executer(Acteurs::avecRole('comptable', $g->organisation), Taux::depuisSaisie('2800'), Taux::depuisSaisie('2800'));
    expect(TauxChange::count())->toBe(1);
});

it('garde les taux en insertion seule', function () {
    membreBiDevise();

    expect(fn () => DB::statement('update taux_change set achat = 1'))->toThrow(QueryException::class, 'insertion seule');
});

it('refuse entre deux membres, dans la même devise, membre non actif ou solde minimum', function () {
    [$g, $usd, $cdf] = membreBiDevise();
    $autre = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), $cdf->type);
    $usd2 = app(OuvrirCompteEpargne::class)->executer($g->gerant, $usd->membre, $usd->type);
    actingAs($g->caissier);
    $dix = Montant::depuisSaisie('10', Devise::USD);

    expect(fn () => app(Convertir::class)->executer($g->caissier, $usd, $autre, $dix, (string) Str::uuid()))->toThrow(CompteEpargneRefuse::class)
        ->and(fn () => app(Convertir::class)->executer($g->caissier, $usd, $usd2, $dix, (string) Str::uuid()))->toThrow(CompteEpargneRefuse::class)
        ->and(fn () => app(Convertir::class)->executer($g->caissier, $usd, $cdf, Montant::depuisSaisie('100,01', Devise::USD), (string) Str::uuid()))->toThrow(CompteEpargneRefuse::class);

    app(ChangerStatutMembre::class)->executer($g->gerant, $usd->membre, StatutMembre::Suspendu);
    actingAs($g->caissier);
    expect(fn () => app(Convertir::class)->executer($g->caissier, $usd->fresh(['membre', 'type', 'compte']) ?? $usd, $cdf, $dix, (string) Str::uuid()))
        ->toThrow(CompteEpargneRefuse::class);
});

it('réserve la conversion aux rôles du virement', function () {
    [$g, $usd, $cdf] = membreBiDevise();

    app(Convertir::class)->executer(Acteurs::avecRole('comptable', $g->organisation), $usd, $cdf, Montant::depuisSaisie('1', Devise::USD), (string) Str::uuid());
})->throws(AuthorizationException::class);

it('émet un reçu pour chacun des deux comptes', function () {
    [$g, $usd, $cdf] = membreBiDevise();

    app(Convertir::class)->executer($g->caissier, $usd, $cdf, Montant::depuisSaisie('10', Devise::USD), (string) Str::uuid());

    expect(MouvementEpargne::whereIn('type', ['change_sortant', 'change_entrant'])->count())->toBe(2)
        ->and(Recu::count())->toBe(2);
});
