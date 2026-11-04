<?php

declare(strict_types=1);

use App\Domain\Change\Actions\SaisirTaux;
use App\Domain\Change\Models\TauxChange;
use App\Domain\Change\Taux;
use App\Domain\Epargne\Actions\Deposer;
use App\Domain\Finance\Models\Operation;
use App\Domain\Organisation\Models\Organisation;
use App\Support\Temps\Horloge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Credit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

function fixerFuseau(Organisation $organisation, string $fuseau): void
{
    Organisation::query()->whereKey($organisation->id)->update(['fuseau' => $fuseau]);
    Horloge::oublier();
}

it('donne Kinshasa par défaut et calcule le jour local de chaque organisation', function () {
    $c = Credit::preparer();
    expect(Organisation::query()->findOrFail($c->g->organisation->id)->getAttribute('fuseau'))->toBe('Africa/Kinshasa');

    travelTo(CarbonImmutable::parse('2026-03-31 22:30:00', 'UTC'));
    expect(Horloge::aujourdhui($c->g->organisation->id)->toDateString())->toBe('2026-03-31'); // 23 h 30 à Kinshasa
    fixerFuseau($c->g->organisation, 'Africa/Lubumbashi');
    expect(Horloge::aujourdhui($c->g->organisation->id)->toDateString())->toBe('2026-04-01'); // 0 h 30 à Lubumbashi
});

it('date une opération au jour local de son organisation, pas au jour UTC du serveur', function () {
    $c = Credit::preparer();
    fixerFuseau($c->g->organisation, 'Africa/Lubumbashi');
    travelTo(CarbonImmutable::parse('2026-03-31 22:30:00', 'UTC'));

    app(Deposer::class)->executer($c->g->caissier, $c->compte, Credit::usd('10'), (string) Str::uuid());

    expect(Operation::query()->latest('id')->firstOrFail()->date_valeur->toDateString())->toBe('2026-04-01');
});

it('applique le taux de change du jour local', function () {
    $c = Credit::preparer();
    fixerFuseau($c->g->organisation, 'Africa/Lubumbashi');
    travelTo(CarbonImmutable::parse('2026-03-31 22:30:00', 'UTC'));
    auth()->setUser($c->g->gerant);

    app(SaisirTaux::class)->executer($c->g->gerant, Taux::depuisSaisie('2800'), Taux::depuisSaisie('2850'));

    expect(TauxChange::query()->latest('id')->firstOrFail()->date_application->toDateString())->toBe('2026-04-01')
        ->and(TauxChange::duJour())->not->toBeNull();
});

it('affiche les heures dans le fuseau de l’organisation', function () {
    $c = Credit::preparer();
    fixerFuseau($c->g->organisation, 'Africa/Lubumbashi');

    expect(Horloge::local(CarbonImmutable::parse('2026-03-31 22:30:00', 'UTC'), $c->g->organisation->id)->format('d/m/Y H:i'))->toBe('01/04/2026 00:30');
});

it('laisse l’administrateur choisir le fuseau de l’organisation', function () {
    $admin = Acteurs::avecRole('admin');
    actingAs($admin);

    get('/organisation')->assertOk()->assertSee('Africa/Kinshasa');
    post('/organisation/fuseau', ['fuseau' => 'Mars/Olympus'])->assertSessionHasErrors('fuseau');
    post('/organisation/fuseau', ['fuseau' => 'Africa/Lubumbashi'])->assertRedirect('/organisation');

    expect(Organisation::query()->findOrFail($admin->organisation_id)->getAttribute('fuseau'))->toBe('Africa/Lubumbashi');
});
