<?php

declare(strict_types=1);

use App\Domain\Comptabilite\Actions\ModifierCorrespondance;
use App\Domain\Comptabilite\PlanSyscohada;
use App\Domain\Comptabilite\Services\EtatsComptables;
use App\Domain\Epargne\Actions\Deposer;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Epargne\Actions\Retirer;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Tests\Support\Credit;
use Tests\Support\Epargnant;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

/**
 * Épargne USD : 500 déposés à la préparation (le 10/01), +100 le 20/01, −30 le 05/02 ; épargne CDF : 10 000 le 05/02.
 *
 * @return array{Credit, CompteEpargne}
 */
function exerciceComptable(): array
{
    travelTo('2026-01-10 09:00');
    $c = Credit::preparer();
    travelTo('2026-01-20 09:00');
    app(Deposer::class)->executer($c->g->caissier, $c->compte, Credit::usd('100'), (string) Str::uuid());
    travelTo('2026-02-05 09:00');
    app(Retirer::class)->executer($c->g->caissier, $c->compte, Credit::usd('30'), (string) Str::uuid());
    $cdf = app(OuvrirCompteEpargne::class)->executer($c->g->gerant, $c->membre, Epargnant::type($c->g->gerant, 'EPC', Devise::CDF));
    app(Deposer::class)->executer($c->g->caissier, $cdf, Montant::depuisSaisie('10000', Devise::CDF), (string) Str::uuid());
    travelTo('2026-03-01 09:00');

    return [$c, $cdf];
}

/**
 * @return array{CarbonImmutable, CarbonImmutable}
 */
function periode(string $du, string $au): array
{
    return [CarbonImmutable::parse($du), CarbonImmutable::parse($au)];
}

it('propose une correspondance SYSCOHADA par défaut, modifiable par le comptable seul', function () {
    [$c] = exerciceComptable();
    $caisse = $c->g->caisse->compte(Devise::USD);

    expect(app(PlanSyscohada::class)->numero($caisse))->toBe(PlanSyscohada::PROPOSITION['caisse'][0])
        ->and(app(PlanSyscohada::class)->numero($c->compte->compte))->toBe(PlanSyscohada::PROPOSITION['epargne'][0]);

    app(ModifierCorrespondance::class)->executer($c->comptable, 'caisse', '571', 'Caisse');
    expect(app(PlanSyscohada::class)->numero($caisse))->toBe('571')
        ->and(fn () => app(ModifierCorrespondance::class)->executer($c->g->gerant, 'caisse', '5711', 'Caisse'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ModifierCorrespondance::class)->executer($c->comptable, 'caisse', '57a', 'Caisse'))->toThrow(InvalidArgumentException::class);
});

it('établit la balance par devise : ouverture, mouvements de la période, clôture, et elle est équilibrée', function () {
    exerciceComptable();
    [$du, $au] = periode('2026-02-01', '2026-02-28');

    $balance = app(EtatsComptables::class)->balance(Devise::USD, $du, $au);
    $caisse = collect($balance->lignes)->firstWhere('rubrique', 'caisse');
    $epargne = collect($balance->lignes)->firstWhere('rubrique', 'epargne');

    expect($caisse->ouverture->centimes)->toBe(60_000)      // solde débiteur 600
        ->and($caisse->credit->centimes)->toBe(3_000)
        ->and($caisse->cloture->centimes)->toBe(57_000)
        ->and($epargne->ouverture->centimes)->toBe(-60_000) // solde créditeur 600
        ->and($epargne->debit->centimes)->toBe(3_000)
        ->and($epargne->cloture->centimes)->toBe(-57_000)
        ->and($balance->totalDebit->egal($balance->totalCredit))->toBeTrue()
        ->and($balance->totalDebit->centimes)->toBe(3_000);

    $cdf = app(EtatsComptables::class)->balance(Devise::CDF, $du, $au);
    expect(collect($cdf->lignes)->firstWhere('rubrique', 'caisse')?->debit->formate())->toBe('10 000,00 CDF')
        ->and(collect($cdf->lignes)->pluck('rubrique')->all())->not->toContain('pret');
});

it('produit le grand livre d’une rubrique avec le solde courant', function () {
    exerciceComptable();
    [$du, $au] = periode('2026-01-15', '2026-02-28');

    $livre = app(EtatsComptables::class)->grandLivre('caisse', Devise::USD, $du, $au);

    expect($livre->ouverture->centimes)->toBe(50_000)
        ->and(array_map(fn ($l) => [$l->debit->centimes, $l->credit->centimes, $l->solde->centimes], $livre->lignes))
        ->toBe([[10_000, 0, 60_000], [0, 3_000, 57_000]])
        ->and($livre->cloture->centimes)->toBe(57_000)
        ->and($livre->lignes[0]->date->toDateString())->toBe('2026-01-20');
});

it('liste au journal les opérations de la période avec leurs écritures, dans la devise demandée', function () {
    exerciceComptable();
    [$du, $au] = periode('2026-02-01', '2026-02-28');

    $journal = app(EtatsComptables::class)->journal(Devise::USD, $du, $au);

    expect($journal->total())->toBe(1)
        ->and($journal->items()[0]->type)->toBe('retrait')
        ->and($journal->items()[0]->ecritures)->toHaveCount(2);
});

it('montre les états au comptable et au gérant, jamais au caissier', function () {
    [$c] = exerciceComptable();

    foreach ([$c->comptable, $c->g->gerant] as $acteur) {
        actingAs($acteur);
        get('/comptabilite/balance?devise=USD&du=2026-02-01&au=2026-02-28')->assertOk()->assertSeeText(PlanSyscohada::PROPOSITION['caisse'][0])->assertSeeText('570,00 USD');
        get('/comptabilite/grand-livre?rubrique=caisse&devise=USD&du=2026-01-01&au=2026-02-28')->assertOk()->assertSeeText('Solde d’ouverture');
        get('/comptabilite/journal?devise=USD&du=2026-02-01&au=2026-02-28')->assertOk()->assertSeeText('retrait');
    }
    get('/comptabilite/balance?devise=XXX&du=2026-02-01&au=2026-01-01')->assertSessionHasErrors(['devise', 'au']);
    actingAs($c->comptable);
    get('/comptabilite/plan')->assertOk()->assertSee('value="Caisses"', false);

    actingAs($c->g->caissier);
    get('/comptabilite/balance?devise=USD&du=2026-02-01&au=2026-02-28')->assertForbidden();
});
