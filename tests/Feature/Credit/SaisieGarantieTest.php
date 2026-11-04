<?php

declare(strict_types=1);

use App\Domain\Credit\Actions\DecaisserPret;
use App\Domain\Credit\Actions\DeciderSaisieGarantie;
use App\Domain\Credit\Actions\DemanderSaisieGarantie;
use App\Domain\Credit\CreditRefuse;
use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\SaisieGarantie;
use App\Domain\Epargne\Models\BlocageEpargne;
use App\Domain\Finance\MoteurFinancier;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\Acteurs;
use Tests\Support\Credit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

/**
 * Prêt de 1 000 USD décaissé le 15/01 (garantie 200 bloquée) ; produit : prélèvement possible après 30 jours de retard.
 *
 * @return array{Credit, Pret}
 */
function pretEnDefaut(?int $delai = 30): array
{
    travelTo('2026-01-15 09:00');
    $c = Credit::preparer(['delai_saisie_garantie_jours' => $delai]);
    $pret = $c->approuve('1000', 10);
    app(DecaisserPret::class)->executer($c->comptable, $pret);

    return [$c, $pret->refresh()];
}

it('prélève la garantie pour les échéances en retard : demandée par le gérant, validée par le comptable', function () {
    [$c, $pret] = pretEnDefaut();
    travelTo('2026-03-20 10:00'); // échéance du 15/02 impayée depuis 33 jours ; 257 USD échus

    $saisie = app(DemanderSaisieGarantie::class)->executer($c->g->gerant, $pret, 'Membre injoignable depuis février');
    expect($saisie->montant->formate())->toBe('200,00 USD') // la garantie, inférieure à l'échu
        ->and(fn () => app(DeciderSaisieGarantie::class)->valider($c->g->gerant, $saisie, 'moi'))->toThrow(AuthorizationException::class);

    app(DeciderSaisieGarantie::class)->valider($c->comptable, $saisie, 'Vu le retard');

    $echeances = EcheancePret::where('pret_id', $pret->id)->orderBy('rang')->get();
    expect($saisie->refresh()->statut)->toBe('validee')
        ->and($echeances[0]->payee_le)->not->toBeNull()                     // 130 : intérêt 30, capital 100
        ->and($echeances[1]->interet_paye->formate())->toBe('27,00 USD')     // 70 : intérêt 27, capital 43
        ->and($echeances[1]->capital_paye->formate())->toBe('43,00 USD')
        ->and(app(MoteurFinancier::class)->soldeReel($c->compte->compte)->formate())->toBe('1 300,00 USD')
        ->and(BlocageEpargne::sole()->leve_le)->not->toBeNull();
});

it('ne prélève que l’échu quand la garantie le dépasse ; le reste de la garantie reste bloqué', function () {
    travelTo('2026-01-15 09:00');
    $c = Credit::preparer(['garantie_epargne' => '50', 'delai_saisie_garantie_jours' => 30]); // garantie 500
    $pret = $c->approuve('1000', 10);
    app(DecaisserPret::class)->executer($c->comptable, $pret);
    travelTo('2026-03-18 10:00'); // échus : 130 (15/02) + 127 (15/03)

    $saisie = app(DemanderSaisieGarantie::class)->executer($c->g->gerant, $pret->refresh(), 'Retard');
    expect($saisie->montant->formate())->toBe('257,00 USD');
    app(DeciderSaisieGarantie::class)->valider($c->comptable, $saisie, 'ok');

    $blocage = BlocageEpargne::sole();
    expect($blocage->leve_le)->toBeNull()->and($blocage->montant->formate())->toBe('243,00 USD');
});

it('refuse avant le délai du produit, sans délai fixé, ou quand rien n’est échu', function () {
    [$c, $pret] = pretEnDefaut();
    travelTo('2026-03-10 10:00'); // 23 jours de retard
    expect(fn () => app(DemanderSaisieGarantie::class)->executer($c->g->gerant, $pret, 'Retard'))->toThrow(CreditRefuse::class, '30 jours');

    auth()->forgetUser();
    [$c2, $pret2] = pretEnDefaut(null);
    travelTo('2026-06-20 10:00');
    expect(fn () => app(DemanderSaisieGarantie::class)->executer($c2->g->gerant, $pret2, 'Retard'))->toThrow(CreditRefuse::class, 'produit')
        ->and(fn () => app(DemanderSaisieGarantie::class)->executer($c2->g->caissier, $pret2, 'Retard'))->toThrow(AuthorizationException::class);
});

it('propose le prélèvement depuis la fiche du prêt', function () {
    [$c, $pret] = pretEnDefaut();
    travelTo('2026-03-20 10:00');

    actingAs($c->g->gerant);
    get("/prets/{$pret->id}")->assertOk()->assertSeeText('Prélever la garantie');
    post("/prets/{$pret->id}/saisie-garantie", ['motif' => 'Injoignable'])->assertRedirect("/prets/{$pret->id}");

    actingAs($c->comptable);
    get("/prets/{$pret->id}")->assertSeeText('200,00 USD')->assertSeeText('Valider le prélèvement');
    post('/saisies-garantie/'.SaisieGarantie::sole()->id.'/valider', ['motif' => 'Vu'])->assertRedirect("/prets/{$pret->id}");
    expect(SaisieGarantie::sole()->statut)->toBe('validee');

    actingAs(Acteurs::avecRole('caissier', $c->g->organisation));
    post("/prets/{$pret->id}/saisie-garantie", ['motif' => 'x'])->assertForbidden();
});
