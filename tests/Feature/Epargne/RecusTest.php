<?php

declare(strict_types=1);

use App\Domain\Caisse\Actions\OuvrirSession;
use App\Domain\Caisse\Billetage;
use App\Domain\Epargne\Actions\Deposer;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Epargne\Actions\Retirer;
use App\Domain\Epargne\Actions\Virer;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\Recu;
use App\Domain\Epargne\Recus\GenererRecuPdf;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Epargnant;
use Tests\Support\Guichet;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * @return array{Guichet, CompteEpargne}
 */
function guichetPourRecus(): array
{
    Storage::fake('local');
    $g = Guichet::creer();
    $type = Epargnant::type($g->gerant, 'EPV', Devise::USD, ['frais_retrait' => '0,50']);
    $compte = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), $type);
    actingAs($g->caissier);
    app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());

    return [$g, $compte];
}

function usdR(string $m): Montant
{
    return Montant::depuisSaisie($m, Devise::USD);
}

it('émet un reçu numéroté par mouvement, avec le solde après l’opération', function () {
    [$g, $compte] = guichetPourRecus();

    $depot = app(Deposer::class)->executer($g->caissier, $compte, usdR('100'), (string) Str::uuid());
    $retrait = app(Retirer::class)->executer($g->caissier, $compte, usdR('30'), (string) Str::uuid());

    $recus = Recu::orderBy('id')->get();
    expect($recus->pluck('numero')->all())->toBe(['REC-000001', 'REC-000002'])
        ->and($recus[0]->mouvement_epargne_id)->toBe($depot->id)
        ->and($recus[0]->solde_apres->formate())->toBe('100,00 USD')
        ->and($recus[1]->mouvement_epargne_id)->toBe($retrait->id)
        ->and($recus[1]->solde_apres->formate())->toBe('69,50 USD');
});

it('émet un reçu pour chaque compte d’un virement', function () {
    [$g, $source] = guichetPourRecus();
    app(Deposer::class)->executer($g->caissier, $source, usdR('50'), (string) Str::uuid());
    $destination = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), $source->type);
    actingAs($g->caissier);

    app(Virer::class)->executer($g->caissier, $source, $destination, usdR('20'), (string) Str::uuid());

    expect(Recu::count())->toBe(3)
        ->and(Recu::latest('id')->limit(2)->get()->map(fn (Recu $r) => $r->solde_apres->formate())->sort()->values()->all())
        ->toBe(['20,00 USD', '30,00 USD']);
});

it('ne crée pas de second reçu si l’opération est rejouée', function () {
    [$g, $compte] = guichetPourRecus();
    $cle = (string) Str::uuid();

    app(Deposer::class)->executer($g->caissier, $compte, usdR('10'), $cle);
    app(Deposer::class)->executer($g->caissier, $compte, usdR('10'), $cle);

    expect(Recu::count())->toBe(1);
});

it('génère le PDF en file d’attente, après validation de la transaction', function () {
    Queue::fake();
    [$g, $compte] = guichetPourRecus();

    app(Deposer::class)->executer($g->caissier, $compte, usdR('10'), (string) Str::uuid());

    Queue::assertPushed(GenererRecuPdf::class, fn (GenererRecuPdf $job) => $job->afterCommit === true);
    expect(Recu::sole()->chemin)->toBeNull();
});

it('produit un PDF A5 stocké hors du dossier public', function () {
    [$g, $compte] = guichetPourRecus();

    app(Deposer::class)->executer($g->caissier, $compte, usdR('10'), (string) Str::uuid());

    $recu = Recu::sole();
    expect($recu->chemin)->toStartWith("recus/{$g->organisation->id}/")
        ->and(Storage::disk('local')->get((string) $recu->chemin))->toStartWith('%PDF');
});

it('met toutes les mentions demandées sur le reçu', function () {
    [$g, $compte] = guichetPourRecus();
    app(Deposer::class)->executer($g->caissier, $compte, usdR('125,50'), (string) Str::uuid());

    $html = app(GenererRecuPdf::class, ['recuId' => Recu::sole()->id])->html();

    expect($html)->toContain('REC-000001')
        ->toContain(e($g->organisation->nom))
        ->toContain('BUK')
        ->toContain(e($compte->membre->nomComplet()))
        ->toContain($compte->numero)
        ->toContain('Dépôt')
        ->toContain('125,50 USD')
        ->toContain('cent vingt-cinq dollars américains et cinquante cents')
        ->toContain('Solde après opération')
        ->toContain(e($g->caissier->name))
        ->toContain('Signature du membre')
        ->toContain('Signature du caissier')
        ->toContain('size: A5');
});

it('télécharge le reçu pour le personnel de l’organisation, 404 ailleurs', function () {
    [$g, $compte] = guichetPourRecus();
    app(Deposer::class)->executer($g->caissier, $compte, usdR('10'), (string) Str::uuid());
    $recu = Recu::sole();

    get("/recus/{$recu->id}")->assertOk()->assertHeader('content-type', 'application/pdf');
    get("/comptes-epargne/{$compte->id}")->assertSee('href="'.url("/recus/{$recu->id}").'"', false);

    auth()->forgetUser();
    actingAs(Acteurs::avecRole('gerant'));
    get("/recus/{$recu->id}")->assertNotFound();
});

it('indique que le reçu est en préparation tant que le PDF n’existe pas', function () {
    Queue::fake();
    [$g, $compte] = guichetPourRecus();
    app(Deposer::class)->executer($g->caissier, $compte, usdR('10'), (string) Str::uuid());

    get('/recus/'.Recu::sole()->id)->assertRedirect()->assertSessionHas('statut', 'Reçu en préparation, réessayez dans un instant.');
});

it('intègre le logo de l’organisation dans le reçu, sans accès réseau', function () {
    [$g, $compte] = guichetPourRecus();
    $chemin = UploadedFile::fake()->image('logo.png', 120, 40)->store("organisations/{$g->organisation->id}", 'local');
    $g->organisation->forceFill(['logo_chemin' => $chemin])->save();
    actingAs($g->caissier);
    app(Deposer::class)->executer($g->caissier, $compte, usdR('5'), (string) Str::uuid());

    $html = app(GenererRecuPdf::class, ['recuId' => Recu::sole()->id])->html();

    expect($html)->toContain('src="data:image/png;base64,')
        ->and(str_contains($html, 'http'))->toBeFalse();
});
