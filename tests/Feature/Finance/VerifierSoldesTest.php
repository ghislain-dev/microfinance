<?php

declare(strict_types=1);

use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Support\Audit\LigneAudit;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\DeuxOrganisations;
use Tests\Support\GrandLivre;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

/**
 * Deux organisations avec chacune un dépôt de 40 USD.
 *
 * @return array{DeuxOrganisations, Compte, Compte}
 */
function deuxGrandsLivres(): array
{
    $o = DeuxOrganisations::creer();
    $comptes = [];
    foreach ([$o->userA, $o->userB] as $i => $utilisateur) {
        $caisse = GrandLivre::compte($utilisateur, NatureCompte::Caisse);
        $epargne = GrandLivre::compte($utilisateur, NatureCompte::Epargne);
        actingAs($utilisateur);
        $m = Montant::depuisSaisie('40', Devise::USD);
        app(MoteurFinancier::class)->executer("d{$i}", 'depot', [LigneEcriture::debit($caisse, $m), LigneEcriture::credit($epargne, $m)]);
        $comptes[] = $epargne;
    }
    Auth::forgetUser(); // commande planifiée : personne n'est connecté

    return [$o, $comptes[0], $comptes[1]];
}

it('ne signale rien quand les caches correspondent aux écritures', function () {
    deuxGrandsLivres();

    artisan('finance:verifier-soldes')->expectsOutputToContain('Aucun écart')->assertSuccessful();
});

it('signale un cache faux, dans chaque organisation, sans le corriger', function () {
    $journal = Log::spy();
    [, $epargneA, $epargneB] = deuxGrandsLivres();
    DB::table('comptes')->where('id', $epargneB->id)->update(['solde_cache' => 999]);

    artisan('finance:verifier-soldes')
        ->expectsOutputToContain("{$epargneB->numero} : cache 9,99 USD, grand livre 40,00 USD")
        ->assertFailed();

    expect(Compte::withoutGlobalScopes()->findOrFail($epargneB->id)->solde_cache->centimes)->toBe(999);
    $journal->shouldHaveReceived('critical')->withArgs(fn (string $message) => str_contains($message, 'solde'))->atLeast()->once();
});

it('réaligne le cache avec --corriger et le journalise', function () {
    [, , $epargneB] = deuxGrandsLivres();
    DB::table('comptes')->where('id', $epargneB->id)->update(['solde_cache' => 999]);

    artisan('finance:verifier-soldes', ['--corriger' => true])->assertFailed(); // l'écart reste signalé

    expect(Compte::withoutGlobalScopes()->findOrFail($epargneB->id)->solde_cache->centimes)->toBe(4000)
        ->and(LigneAudit::withoutGlobalScopes()->where('action', 'comptes.cache_corrige')->sole()->avant)
        ->toBe(['solde_cache' => 999]);
    artisan('finance:verifier-soldes')->assertSuccessful();
});

it('signale une organisation dont les débits ne valent pas les crédits', function () {
    [$o, $epargneA] = deuxGrandsLivres();
    // Écriture orpheline glissée hors moteur (ce que le moteur rend impossible) :
    $op = GrandLivre::operationBrute($o->userA);
    GrandLivre::ecritureBrute($op, $epargneA, 0, 100);
    Auth::forgetUser();

    artisan('finance:verifier-soldes')->expectsOutputToContain('déséquilibr')->assertFailed();
});

it('est planifiée chaque nuit', function () {
    $evenement = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'finance:verifier-soldes'));

    expect($evenement)->not->toBeNull()
        ->and($evenement->expression)->toBe('0 2 * * *');
});
