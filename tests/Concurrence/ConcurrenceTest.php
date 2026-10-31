<?php

declare(strict_types=1);

use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\Models\Ecriture;
use App\Domain\Finance\Models\Operation;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Organisation\Models\Agence;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\DeuxOrganisations;
use Tests\Support\GrandLivre;

use function Pest\Laravel\artisan;

/**
 * Lance N processus qui démarrent au même instant ; renvoie leurs sorties.
 *
 * @param  Closure(int): list<string>  $arguments
 * @return list<string>
 */
function enParallele(int $nombre, Closure $arguments): array
{
    $depart = (string) (microtime(true) + 3); // laisse le temps à tous les processus de démarrer
    $script = base_path('tests/Concurrence/scripts/operation.php');

    $resultats = Process::pool(function (Pool $pool) use ($nombre, $arguments, $depart, $script) {
        foreach (range(1, $nombre) as $i) {
            $pool->path(base_path())->timeout(120)->command([PHP_BINARY, $script, $depart, ...$arguments($i)]);
        }
    })->start()->wait();

    return array_values(array_map(fn ($r) => trim($r->output()).trim($r->errorOutput()), $resultats->collect()->all()));
}

/**
 * Épargne de 10,00 USD déposée en caisse, données validées en base.
 *
 * @return array{Compte, Compte}
 */
function epargneDeDixDollars(): array
{
    $o = DeuxOrganisations::creer();
    $caisse = GrandLivre::compte($o->userA, NatureCompte::Caisse);
    $epargne = GrandLivre::compte($o->userA, NatureCompte::Epargne);
    $dix = Montant::depuisSaisie('10', Devise::USD);
    app(MoteurFinancier::class)->executer('depot-initial', 'depot', [LigneEcriture::debit($caisse, $dix), LigneEcriture::credit($epargne, $dix)]);
    Auth::forgetUser();

    return [$caisse, $epargne];
}

it('ne rend jamais un compte négatif avec 50 retraits simultanés', function () {
    [$caisse, $epargne] = epargneDeDixDollars();

    $sorties = enParallele(50, fn (int $i) => ["retrait-{$i}", (string) $epargne->id, (string) $caisse->id, '100']);

    $comptage = array_count_values($sorties);
    expect($comptage)->toEqual(['OK' => 10, 'REFUS:SoldeInsuffisant' => 40]) // ordre des clés indifférent
        ->and(Compte::withoutGlobalScopes()->findOrFail($epargne->id)->solde_cache->centimes)->toBe(0)
        ->and(app(MoteurFinancier::class)->soldeReel($epargne)->centimes)->toBe(0)
        ->and(Operation::withoutGlobalScopes()->where('type', 'retrait')->count())->toBe(10)
        ->and((int) Ecriture::withoutGlobalScopes()->sum('debit'))->toBe((int) Ecriture::withoutGlobalScopes()->sum('credit'));
    artisan('finance:verifier-soldes')->assertSuccessful();
})->group('mysql');

it('ne crée qu’une opération pour 50 envois simultanés de la même clé', function () {
    [$caisse, $epargne] = epargneDeDixDollars();
    $cle = (string) Str::uuid();

    $sorties = enParallele(50, fn () => [$cle, (string) $epargne->id, (string) $caisse->id, '100']);

    expect(array_count_values($sorties))->toBe(['OK' => 50]) // chacun reçoit la même opération
        ->and(Operation::withoutGlobalScopes()->where('idempotency_key', $cle)->count())->toBe(1)
        ->and(app(MoteurFinancier::class)->soldeReel($epargne)->centimes)->toBe(900)
        ->and(Compte::withoutGlobalScopes()->findOrFail($epargne->id)->solde_cache->centimes)->toBe(900);
    artisan('finance:verifier-soldes')->assertSuccessful();
})->group('mysql');

it('attribue des numéros de membre sans trou ni doublon avec 20 inscriptions simultanées', function () {
    $caissier = Acteurs::avecRole('caissier');
    $agence = Agence::factory()->for($caissier->organisation)->create(['code' => 'BUK']);
    Auth::forgetUser();
    $depart = (string) (microtime(true) + 3);
    $script = base_path('tests/Concurrence/scripts/inscription.php');

    $sorties = Process::pool(function (Pool $pool) use ($depart, $script, $caissier, $agence) {
        foreach (range(1, 20) as $i) {
            $pool->path(base_path())->timeout(120)
                ->command([PHP_BINARY, $script, $depart, (string) $caissier->id, (string) $agence->id, (string) $i]);
        }
    })->start()->wait()->collect()->map(fn ($r) => trim($r->output()).trim($r->errorOutput()))->sort()->values()->all();

    expect($sorties)->toBe(array_map(fn (int $i) => 'BUK-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT), range(1, 20)));
})->group('mysql');
