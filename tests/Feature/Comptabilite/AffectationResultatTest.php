<?php

declare(strict_types=1);

use App\Domain\Comptabilite\Actions\DeciderAffectation;
use App\Domain\Comptabilite\Actions\ProposerAffectation;
use App\Domain\Comptabilite\ClotureRefusee;
use App\Domain\Comptabilite\Models\AffectationResultat;
use App\Domain\Comptabilite\Services\ResultatAnnuel;
use App\Domain\Credit\Actions\DecaisserPret;
use App\Domain\Credit\Actions\RembourserPret;
use App\Domain\Credit\ModeRemboursement;
use App\Domain\Finance\Actions\CloturerPeriode;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Organisation\Models\Organisation;
use App\Support\Montant\Devise;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Credit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

/**
 * 2026 : 165 USD d'intérêts encaissés sur un prêt remboursé ; décembre 2026 clôturé.
 */
function anneeCloturee(): Credit
{
    travelTo('2026-01-15 09:00');
    $c = Credit::preparer();
    $pret = $c->approuve('1000', 10);
    app(DecaisserPret::class)->executer($c->comptable, $pret);
    travelTo('2026-02-01 09:00');
    app(RembourserPret::class)->executer($c->g->caissier, $pret->refresh(), Credit::usd('1165'), ModeRemboursement::Especes, (string) Str::uuid());
    travelTo('2027-01-05 09:00');
    app(CloturerPeriode::class)->executer(Organisation::query()->findOrFail($c->g->organisation->id), CarbonImmutable::parse('2026-12-31'), 'test');

    return $c;
}

function soldeDuCompteNumero(string $numero): string
{
    return app(MoteurFinancier::class)->soldeReel(Compte::query()->where('numero', $numero)->firstOrFail())->formate();
}

it('calcule le résultat de l’année par devise : produits moins charges', function () {
    $c = anneeCloturee();

    expect(app(ResultatAnnuel::class)->calculer(2026, Devise::USD)->formate())->toBe('165,00 USD')
        ->and(app(ResultatAnnuel::class)->calculer(2026, Devise::CDF)->formate())->toBe('0,00 CDF');
});

it('affecte tout ou partie du résultat aux excédents à répartir : proposé par le comptable, validé par le gérant', function () {
    $c = anneeCloturee();

    $affectation = app(ProposerAffectation::class)->executer($c->comptable, 2026, Credit::usd('100'));
    expect($affectation->statut)->toBe('en_attente')
        ->and(fn () => app(DeciderAffectation::class)->valider($c->comptable, $affectation, 'moi'))->toThrow(AuthorizationException::class);

    app(DeciderAffectation::class)->valider($c->g->gerant, $affectation, 'Décision de l’assemblée');

    expect($affectation->refresh()->statut)->toBe('validee')
        ->and(soldeDuCompteNumero('EXCEDENTS-A-REPARTIR-USD'))->toBe('100,00 USD')
        ->and(app(ResultatAnnuel::class)->restantAAffecter(2026, Devise::USD)->formate())->toBe('65,00 USD');
});

it('refuse une affectation avant la clôture de décembre, au-delà du résultat restant, ou en double', function () {
    travelTo('2026-06-01 09:00');
    $c = Credit::preparer();
    expect(fn () => app(ProposerAffectation::class)->executer($c->comptable, 2026, Credit::usd('1')))->toThrow(ClotureRefusee::class, 'décembre');

    auth()->forgetUser();
    $c = anneeCloturee();
    expect(fn () => app(ProposerAffectation::class)->executer($c->comptable, 2026, Credit::usd('165,01')))->toThrow(ClotureRefusee::class)
        ->and(fn () => app(ProposerAffectation::class)->executer($c->g->gerant, 2026, Credit::usd('10')))->toThrow(AuthorizationException::class);

    $a = app(ProposerAffectation::class)->executer($c->comptable, 2026, Credit::usd('100'));
    expect(fn () => app(ProposerAffectation::class)->executer($c->comptable, 2026, Credit::usd('10')))->toThrow(ClotureRefusee::class);
    app(DeciderAffectation::class)->rejeter($c->g->gerant, $a, 'À revoir');
    expect(AffectationResultat::query()->where('statut', 'rejetee')->count())->toBe(1);
});

it('alimente ainsi la ristourne, jusque-là impossible faute d’excédents', function () {
    $c = anneeCloturee();
    app(DeciderAffectation::class)->valider($c->g->gerant, app(ProposerAffectation::class)->executer($c->comptable, 2026, Credit::usd('100')), 'AG');
    actingAs($c->g->gerant);

    get('/epargne/remunerations')->assertOk()->assertSeeText('100,00 USD');
});

it('mène l’affectation depuis l’écran des clôtures', function () {
    $c = anneeCloturee();

    actingAs($c->comptable);
    get('/comptabilite/clotures')->assertOk()->assertSeeText('Affectation du résultat')->assertSeeText('165,00 USD');
    post('/comptabilite/affectations', ['annee' => '2026', 'devise' => 'USD', 'montant' => '165'])->assertRedirect('/comptabilite/clotures');
    post('/comptabilite/affectations', ['annee' => 'x', 'devise' => 'USD', 'montant' => 'abc'])->assertSessionHasErrors(['annee', 'montant']);

    actingAs($c->g->gerant);
    post('/comptabilite/affectations/'.AffectationResultat::sole()->id.'/valider', ['motif' => 'AG du 10/01'])->assertRedirect('/comptabilite/clotures');
    expect(soldeDuCompteNumero('EXCEDENTS-A-REPARTIR-USD'))->toBe('165,00 USD');

    actingAs(Acteurs::avecRole('caissier', $c->g->organisation));
    post('/comptabilite/affectations', ['annee' => '2026', 'devise' => 'USD', 'montant' => '1'])->assertForbidden();
});
