<?php

declare(strict_types=1);

use App\Domain\Caisse\Actions\CloturerSession;
use App\Domain\Caisse\Billetage;
use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Comptabilite\Actions\DeciderCloture;
use App\Domain\Comptabilite\Actions\DemanderCloture;
use App\Domain\Comptabilite\ClotureRefusee;
use App\Domain\Comptabilite\Models\DemandeCloture;
use App\Domain\Epargne\Remuneration\Actions\CalculerInterets;
use App\Domain\Finance\Exceptions\PeriodeCloturee;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\ClotureComptable;
use App\Domain\Finance\MoteurFinancier;
use App\Support\Montant\Devise;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Credit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

/**
 * Janvier 2026 avec un dépôt ; la session de caisse est fermée sans écart le 31/01.
 */
function janvierTermine(): Credit
{
    travelTo('2026-01-10 09:00');
    $c = Credit::preparer(); // dépôt de 500 USD le 10/01 (session ouverte par le caissier)
    travelTo('2026-01-31 17:00');
    $session = SessionCaisse::where('caissier_id', $c->g->caissier->id)->sole();
    app(CloturerSession::class)->executer($c->g->caissier, $session, Billetage::depuisSaisie(['USD' => [100 => 5]]));
    travelTo('2026-02-03 09:00');

    return $c;
}

it('clôture un mois : demande du comptable, validation du gérant, plus aucune écriture datée de ce mois', function () {
    $c = janvierTermine();

    $demande = app(DemanderCloture::class)->executer($c->comptable, CarbonImmutable::parse('2026-01-01'));
    expect($demande->statut)->toBe('en_attente')
        ->and(ClotureComptable::count())->toBe(0)
        ->and(fn () => app(DeciderCloture::class)->valider($c->comptable, $demande, 'moi'))->toThrow(AuthorizationException::class);

    app(DeciderCloture::class)->valider($c->g->gerant, $demande, 'Contrôles faits');

    expect($demande->refresh()->statut)->toBe('validee')
        ->and(ClotureComptable::sole()->date_fin->toDateString())->toBe('2026-01-31');
    $moteur = app(MoteurFinancier::class);
    expect(fn () => $moteur->executer((string) Str::uuid(), 'essai', [
        LigneEcriture::debit($c->g->caisse->compte(Devise::USD), Credit::usd('1')),
        LigneEcriture::credit($c->compte->compte, Credit::usd('1')),
    ], dateValeur: CarbonImmutable::parse('2026-01-20')))->toThrow(PeriodeCloturee::class);
});

/**
 * Exécute la demande et renvoie le refus attendu (échoue si la clôture est acceptée).
 */
function refusDeCloture(Closure $demande): ClotureRefusee
{
    try {
        $demande();
    } catch (ClotureRefusee $e) {
        return $e;
    }

    throw new RuntimeException('Clôture acceptée alors qu’elle devait être refusée.');
}

it('refuse la demande tant qu’un contrôle bloque, avec la liste des problèmes', function () {
    travelTo('2026-01-10 09:00');
    $c = Credit::preparer(); // session de caisse restée ouverte
    travelTo('2026-02-03 09:00');

    $refus = refusDeCloture(fn () => app(DemanderCloture::class)->executer($c->comptable, CarbonImmutable::parse('2026-01-01')));
    expect($refus->problemes)->toHaveCount(1)->and($refus->problemes[0])->toContain('session');
    expect(DemandeCloture::count())->toBe(0);
});

it('bloque aussi sur un lot d’intérêts à valider et sur un écart de solde', function () {
    $c = janvierTermine();
    $c->compte->type->forceFill(['taux_interet_pb' => 1_200])->save();
    app(CalculerInterets::class)->executer($c->g->organisation, '2026-01', Devise::USD); // lot en brouillon
    DB::table('comptes')->where('id', $c->compte->compte_id)->increment('solde_cache', 1); // cache faussé

    $refus = refusDeCloture(fn () => app(DemanderCloture::class)->executer($c->comptable, CarbonImmutable::parse('2026-01-01')));
    expect(implode(' ', $refus->problemes))->toContain('intérêts')->toContain('solde');
});

it('ne clôture ni un mois en cours ni un mois déjà clôturé, et revérifie à la validation', function () {
    $c = janvierTermine();

    expect(fn () => app(DemanderCloture::class)->executer($c->comptable, CarbonImmutable::parse('2026-02-01')))->toThrow(ClotureRefusee::class);
    $demande = app(DemanderCloture::class)->executer($c->comptable, CarbonImmutable::parse('2026-01-01'));
    expect(fn () => app(DemanderCloture::class)->executer($c->comptable, CarbonImmutable::parse('2026-01-01')))->toThrow(ClotureRefusee::class);

    // Entre la demande et la validation, une session rouverte puis laissée ouverte sur janvier n'est plus possible,
    // mais un écart de solde peut apparaître : la validation revérifie.
    DB::table('comptes')->where('id', $c->compte->compte_id)->increment('solde_cache', 1);
    expect(fn () => app(DeciderCloture::class)->valider($c->g->gerant, $demande, 'ok'))->toThrow(ClotureRefusee::class)
        ->and(ClotureComptable::count())->toBe(0);

    app(DeciderCloture::class)->rejeter($c->g->gerant, $demande, 'Écart à corriger');
    expect($demande->refresh()->statut)->toBe('rejetee');
});

it('mène la clôture depuis l’écran (comptable puis gérant)', function () {
    $c = janvierTermine();

    actingAs($c->comptable);
    get('/comptabilite/clotures')->assertOk()->assertSeeText('Demander la clôture');
    post('/comptabilite/clotures', ['mois' => '2026-01'])->assertRedirect('/comptabilite/clotures');
    post('/comptabilite/clotures', ['mois' => 'janvier'])->assertSessionHasErrors('mois');
    $demande = DemandeCloture::sole();

    actingAs($c->g->gerant);
    get('/comptabilite/clotures')->assertOk()->assertSeeText('Valider');
    post("/comptabilite/clotures/{$demande->id}/valider", ['motif' => 'Vu'])->assertRedirect('/comptabilite/clotures');
    expect(ClotureComptable::count())->toBe(1);
    get('/comptabilite/clotures')->assertSeeText('31/01/2026');

    actingAs(Acteurs::avecRole('caissier', $c->g->organisation));
    get('/comptabilite/clotures')->assertForbidden();
});
