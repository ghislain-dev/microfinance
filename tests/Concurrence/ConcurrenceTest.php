<?php

declare(strict_types=1);

use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Collecte\Models\Appareil;
use App\Domain\Collecte\Models\OperationSync;
use App\Domain\Credit\Actions\DecaisserPret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\Remboursement;
use App\Domain\Credit\StatutPret;
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
use Tests\Support\Collecte;
use Tests\Support\Credit;
use Tests\Support\DeuxOrganisations;
use Tests\Support\GrandLivre;
use Tests\Support\Guichet;

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

it('n’ouvre qu’une session pour 10 ouvertures simultanées de la même caisse', function () {
    $g = Guichet::creer();
    // 10 caissiers différents, tous rattachés à l'agence de la caisse.
    $caissiers = collect(range(1, 10))->map(function () use ($g) {
        $c = Acteurs::avecRole('caissier', $g->organisation);
        $c->agences()->attach($g->agence, ['organisation_id' => $g->organisation->id]);

        return $c;
    });
    Auth::forgetUser();
    $depart = (string) (microtime(true) + 3);
    $script = base_path('tests/Concurrence/scripts/ouverture.php');

    $sorties = Process::pool(function (Pool $pool) use ($caissiers, $depart, $script, $g) {
        foreach ($caissiers as $caissier) {
            $pool->path(base_path())->timeout(120)->command([PHP_BINARY, $script, $depart, (string) $caissier->id, (string) $g->caisse->id]);
        }
    })->start()->wait()->collect()->map(fn ($r) => trim($r->output()).trim($r->errorOutput()))->all();

    expect(array_count_values($sorties))->toEqual(['OK' => 1, 'REFUS' => 9])
        ->and(SessionCaisse::withoutGlobalScopes()->count())->toBe(1);
})->group('mysql');

it('ne rembourse jamais un prêt au-delà de ce qu’il doit avec 10 remboursements complets simultanés', function () {
    $c = Credit::preparer();
    $pret = $c->approuve('1000', 10);
    app(DecaisserPret::class)->executer($c->comptable, $pret);
    Auth::forgetUser();
    $depart = (string) (microtime(true) + 3);
    $script = base_path('tests/Concurrence/scripts/remboursement.php');

    // 1 000 de capital + 165 d'intérêts prévus : chaque processus tente de tout payer.
    $sorties = Process::pool(function (Pool $pool) use ($depart, $script, $c, $pret) {
        foreach (range(1, 10) as $i) {
            $pool->path(base_path())->timeout(120)
                ->command([PHP_BINARY, $script, $depart, (string) $c->g->caissier->id, (string) $pret->id, '1165', "remb-{$i}"]);
        }
    })->start()->wait()->collect()->map(fn ($r) => trim($r->output()).trim($r->errorOutput()))->all();

    expect(array_count_values($sorties))->toEqual(['OK' => 1, 'REFUS' => 9])
        ->and(Remboursement::withoutGlobalScopes()->count())->toBe(1)
        ->and(Pret::withoutGlobalScopes()->findOrFail($pret->id)->statut)->toBe(StatutPret::Solde);
    artisan('finance:verifier-soldes')->assertSuccessful();
})->group('mysql');

it('n’enregistre qu’une fois une opération de collecte envoyée par 10 synchronisations simultanées', function () {
    $c = Collecte::preparer();
    $c->jeton();
    $appareil = Appareil::withoutGlobalScopes()->sole();
    $operation = ['cle' => (string) Str::uuid(), 'type' => 'depot', 'membre_id' => $c->membres[0]->id,
        'compte_epargne_id' => $c->comptes[0]->id, 'montant_centimes' => 1_000, 'saisi_le' => now()->toIso8601String()];
    Auth::forgetUser();
    $depart = (string) (microtime(true) + 3);
    $script = base_path('tests/Concurrence/scripts/synchronisation.php');

    $sorties = Process::pool(function (Pool $pool) use ($depart, $script, $c, $appareil, $operation) {
        foreach (range(1, 10) as $i) {
            $pool->path(base_path())->timeout(120)
                ->command([PHP_BINARY, $script, $depart, (string) $c->collecteur->id, (string) $appareil->id, json_encode([$operation], JSON_THROW_ON_ERROR)]);
        }
    })->start()->wait()->collect()->map(fn ($r) => trim($r->output()).trim($r->errorOutput()))->all();

    expect(array_count_values($sorties))->toEqual(['cree' => 1, 'deja_traite' => 9])
        ->and(OperationSync::withoutGlobalScopes()->count())->toBe(1)
        ->and(app(MoteurFinancier::class)->soldeReel($c->comptes[0]->compte)->formate())->toBe('110,00 USD');
    artisan('finance:verifier-soldes')->assertSuccessful();
})->group('mysql');
