<?php

declare(strict_types=1);

use App\Domain\Credit\Actions\DecaisserPret;
use App\Domain\Credit\Actions\RembourserPret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\Remboursement;
use App\Domain\Credit\ModeRemboursement;
use App\Domain\Epargne\Models\Recu;
use App\Domain\Epargne\Recus\GenererRecuPdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Credit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(fn () => Storage::fake('local'));

/**
 * @return array{Credit, Pret}
 */
function pretARembourser(): array
{
    travelTo('2026-01-15 09:00');
    $c = Credit::preparer();
    $pret = $c->approuve('1000', 10);
    app(DecaisserPret::class)->executer($c->comptable, $pret);
    travelTo('2026-02-15 10:00');

    return [$c, $pret->refresh()];
}

it('émet un reçu numéroté pour un remboursement en espèces : répartition et reste dû', function () {
    [$c, $pret] = pretARembourser();

    $r = app(RembourserPret::class)->executer($c->g->caissier, $pret, Credit::usd('130'), ModeRemboursement::Especes, (string) Str::uuid());

    $recu = Recu::query()->where('remboursement_pret_id', $r->id)->sole();
    expect($recu->numero)->toStartWith('REC-')
        ->and($recu->solde_apres->formate())->toBe('1 035,00 USD') // 1 165 − 130
        ->and($recu->chemin)->not->toBeNull();

    $html = (new GenererRecuPdf($recu->id))->html();
    expect($html)->toContain('Remboursement de prêt')->toContain($pret->numero)
        ->toContain('30,00 USD')->toContain('100,00 USD')->toContain('1 035,00 USD')
        ->toContain('cent trente dollars américains');
});

it('suit la même numérotation que les reçus d’épargne, et un remboursement depuis l’épargne a ses deux reçus', function () {
    [$c, $pret] = pretARembourser();

    $r = app(RembourserPret::class)->executer($c->g->caissier, $pret, Credit::usd('130'), ModeRemboursement::Epargne, (string) Str::uuid());

    $numeros = Recu::query()->orderBy('sequence')->pluck('numero')->all();
    expect($numeros)->toBe(array_map(fn (int $i) => 'REC-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT), range(1, count($numeros))))
        ->and(Recu::query()->where('remboursement_pret_id', $r->id)->count())->toBe(1)
        ->and(Recu::query()->whereNotNull('mouvement_epargne_id')->latest('sequence')->first()?->mouvement?->type)->toBe('remboursement_credit');
});

it('ne crée pas de second reçu quand le remboursement est rejoué', function () {
    [$c, $pret] = pretARembourser();
    $cle = (string) Str::uuid();

    foreach ([1, 2, 3] as $fois) {
        app(RembourserPret::class)->executer($c->g->caissier, $pret, Credit::usd('50'), ModeRemboursement::Especes, $cle);
    }

    expect(Recu::query()->whereNotNull('remboursement_pret_id')->count())->toBe(1);
});

it('propose le reçu depuis la fiche du prêt', function () {
    [$c, $pret] = pretARembourser();
    app(RembourserPret::class)->executer($c->g->caissier, $pret, Credit::usd('130'), ModeRemboursement::Especes, (string) Str::uuid());
    $recu = Recu::query()->whereNotNull('remboursement_pret_id')->sole();
    actingAs($c->g->caissier);

    get("/prets/{$pret->id}")->assertOk()->assertSeeText($recu->numero);
    get("/recus/{$recu->id}")->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(Remboursement::count())->toBe(1);
});
