<?php

declare(strict_types=1);

use App\Domain\Credit\Actions\AnalyserPret;
use App\Domain\Credit\Actions\DecaisserPret;
use App\Domain\Epargne\Actions\Deposer;
use Illuminate\Support\Str;
use Tests\Support\Credit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

/**
 * Épargne 500 + 25 (dépôts du jour), un prêt de 1 000 décaissé, une demande d'un autre membre au comité.
 */
function activite(): Credit
{
    travelTo('2026-03-10 09:00');
    $c = Credit::preparer();
    app(Deposer::class)->executer($c->g->caissier, $c->compte, Credit::usd('25'), (string) Str::uuid());
    app(DecaisserPret::class)->executer($c->comptable, $c->approuve('1000', 10));
    $autre = Credit::autreEmprunteur($c);
    app(AnalyserPret::class)->executer($c->agent, $autre->demande('200'), 'Bon dossier.');

    return $c;
}

it('montre au gérant l’épargne, le crédit, la caisse et la collecte, les tontines et les membres', function () {
    $c = activite();
    actingAs($c->g->gerant);

    get('/tableau-de-bord')->assertOk()
        ->assertSeeText('Épargne')->assertSeeText('2 025,00 USD')       // 525 + 1 000 décaissés + 500 de l'autre membre
        ->assertSeeText('Dépôts du jour')->assertSeeText('1 025,00 USD')  // 500 + 25 + 500
        ->assertSeeText('Crédit')->assertSeeText('Encours')->assertSeeText('1 000,00 USD')
        ->assertSeeText('Caisse et collecte')->assertSeeText('Sessions de caisse ouvertes : 1')
        ->assertSeeText('Tontines et membres')->assertSeeText('Membres actifs : 2')
        ->assertSeeText('Prêts au comité : 1');
});

it('ne montre que les blocs permis par le rôle', function () {
    $c = activite();

    actingAs($c->g->caissier);
    get('/tableau-de-bord')->assertOk()->assertSeeText('Épargne')->assertSeeText('Caisse et collecte')->assertDontSeeText('Encours');

    actingAs($c->agent);
    get('/tableau-de-bord')->assertOk()->assertSeeText('Encours')->assertDontSeeText('Dépôts du jour')->assertDontSeeText('Sessions de caisse ouvertes');

    actingAs($c->comite);
    get('/tableau-de-bord')->assertOk()->assertSeeText('Prêts au comité : 1')->assertDontSeeText('Encours')->assertDontSeeText('Dépôts du jour');
});
