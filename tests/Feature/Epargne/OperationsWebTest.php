<?php

declare(strict_types=1);

use App\Domain\Caisse\Actions\OuvrirSession;
use App\Domain\Caisse\Billetage;
use App\Domain\Epargne\Actions\FixerSeuil;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\DemandeDecaissement;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\StatutDemande;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Epargnant;
use Tests\Support\Guichet;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Guichet avec un compte d'épargne USD ; le caissier est connecté, caisse ouverte ou non.
 *
 * @return array{Guichet, CompteEpargne}
 */
function compteAuGuichet(bool $caisseOuverte = true): array
{
    $g = Guichet::creer();
    $compte = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), Epargnant::type($g->gerant));
    actingAs($g->caissier);
    if ($caisseOuverte) {
        app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());
    }

    return [$g, $compte];
}

/**
 * @return array{montant: string, cle: string}
 */
function saisie(string $montant): array
{
    return ['montant' => $montant, 'cle' => (string) Str::uuid()];
}

it('dépose depuis la page du compte et affiche le nouveau solde et le mouvement', function () {
    [, $compte] = compteAuGuichet();

    get("/comptes-epargne/{$compte->id}")->assertOk()->assertSee('name="cle"', false)->assertSeeText('Déposer');
    post("/comptes-epargne/{$compte->id}/depots", saisie('125,50'))->assertRedirect("/comptes-epargne/{$compte->id}");

    get("/comptes-epargne/{$compte->id}")->assertOk()->assertSeeText('125,50 USD')->assertSeeText('Dépôt');
});

it('n’enregistre qu’un dépôt si le formulaire est envoyé deux fois', function () {
    [, $compte] = compteAuGuichet();
    $donnees = saisie('10');

    post("/comptes-epargne/{$compte->id}/depots", $donnees);
    post("/comptes-epargne/{$compte->id}/depots", $donnees);

    expect(MouvementEpargne::count())->toBe(1);
});

it('explique qu’il faut ouvrir la caisse', function () {
    [, $compte] = compteAuGuichet(caisseOuverte: false);

    post("/comptes-epargne/{$compte->id}/depots", saisie('10'))->assertSessionHasErrors('montant');
    expect(MouvementEpargne::count())->toBe(0);
});

it('valide le montant saisi', function () {
    [, $compte] = compteAuGuichet();

    post("/comptes-epargne/{$compte->id}/depots", saisie('10,555'))->assertSessionHasErrors('montant');
    post("/comptes-epargne/{$compte->id}/depots", ['montant' => '10', 'cle' => 'pas-un-uuid'])->assertSessionHasErrors('cle');
});

it('affiche un refus du moteur comme une erreur de saisie (solde insuffisant)', function () {
    [, $compte] = compteAuGuichet();

    post("/comptes-epargne/{$compte->id}/retraits", saisie('5'))->assertSessionHasErrors('montant');
});

it('déroule un gros retrait : demande → validation du comptable → paiement au guichet', function () {
    [$g, $compte] = compteAuGuichet();
    app(FixerSeuil::class)->executer($g->gerant, Devise::USD, Montant::depuisSaisie('100', Devise::USD));
    actingAs($g->caissier);
    post("/comptes-epargne/{$compte->id}/depots", saisie('500'));

    post("/comptes-epargne/{$compte->id}/retraits", saisie('300'))->assertRedirect()->assertSessionHas('statut');
    $demande = DemandeDecaissement::sole();

    actingAs(Acteurs::avecRole('comptable', $g->organisation));
    get('/demandes')->assertOk()->assertSeeText($compte->numero)->assertSeeText('300,00 USD');
    post("/demandes/{$demande->id}/approuver", ['motif' => 'Pièce vérifiée'])->assertRedirect('/demandes');

    actingAs($g->caissier);
    get("/comptes-epargne/{$compte->id}")->assertSeeText('Payer');
    post("/demandes/{$demande->id}/payer", ['cle' => (string) Str::uuid()])->assertRedirect("/comptes-epargne/{$compte->id}");

    expect($demande->fresh()?->statut)->toBe(StatutDemande::Executee);
    get("/comptes-epargne/{$compte->id}")->assertSeeText('200,00 USD');
});

it('refuse au demandeur de valider sa propre demande et exige un motif', function () {
    [$g, $compte] = compteAuGuichet();
    app(FixerSeuil::class)->executer($g->gerant, Devise::USD, Montant::depuisSaisie('1', Devise::USD));
    actingAs($g->caissier);
    post("/comptes-epargne/{$compte->id}/depots", saisie('5'));
    post("/comptes-epargne/{$compte->id}/retraits", saisie('2')); // au-delà du seuil de 1 USD
    $demande = DemandeDecaissement::sole();

    post("/demandes/{$demande->id}/approuver", ['motif' => 'moi'])->assertForbidden();
    actingAs($g->gerant);
    post("/demandes/{$demande->id}/rejeter", ['motif' => ''])->assertSessionHasErrors('motif');
    post("/demandes/{$demande->id}/rejeter", ['motif' => 'Non justifié'])->assertRedirect('/demandes');
    expect($demande->fresh()?->statut)->toBe(StatutDemande::Rejetee);
});

it('vire vers le numéro d’un autre compte', function () {
    [$g, $source] = compteAuGuichet();
    $destination = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), $source->type);
    actingAs($g->caissier);
    post("/comptes-epargne/{$source->id}/depots", saisie('50'));

    post("/comptes-epargne/{$source->id}/virements", [...saisie('20'), 'destination' => $destination->numero])
        ->assertRedirect("/comptes-epargne/{$source->id}");
    post("/comptes-epargne/{$source->id}/virements", [...saisie('1'), 'destination' => 'INCONNU-1'])->assertSessionHasErrors('destination');

    get("/comptes-epargne/{$destination->id}")->assertSeeText('20,00 USD')->assertSeeText('Virement reçu');
});

it('laisse le gérant fixer les seuils, pas le caissier', function () {
    [$g] = compteAuGuichet();

    post('/epargne/seuils', ['devise' => 'USD', 'montant' => '500'])->assertForbidden();
    actingAs($g->gerant);
    get('/epargne/seuils')->assertOk();
    post('/epargne/seuils', ['devise' => 'USD', 'montant' => '500'])->assertRedirect('/epargne/seuils');
    get('/epargne/seuils')->assertSeeText('500,00 USD');
});

it('montre le compte au collecteur sans formulaire d’opération', function () {
    [$g, $compte] = compteAuGuichet();
    actingAs(Acteurs::avecRole('collecteur', $g->organisation));

    get("/comptes-epargne/{$compte->id}")->assertOk()->assertDontSee('name="cle"', false);
    post("/comptes-epargne/{$compte->id}/depots", saisie('1'))->assertForbidden();
    get('/demandes')->assertForbidden();
});

it('répond 404 sur le compte ou la demande d’une autre organisation', function () {
    [, $compte] = compteAuGuichet();
    auth()->forgetUser();
    actingAs(Acteurs::avecRole('gerant'));

    get("/comptes-epargne/{$compte->id}")->assertNotFound();
    post("/comptes-epargne/{$compte->id}/depots", saisie('1'))->assertNotFound();
});

it('relie la fiche membre à la page de chaque compte', function () {
    [, $compte] = compteAuGuichet();

    get("/membres/{$compte->membre_id}")->assertSee('href="'.url("/comptes-epargne/{$compte->id}").'"', false);
});

it('ajoute « Demandes à valider » au menu du gérant et du comptable', function (string $role, bool $visible) {
    actingAs(Acteurs::avecRole($role));

    expect(str_contains((string) get('/tableau-de-bord')->getContent(), 'href="'.url('/demandes').'"'))->toBe($visible);
})->with([['gerant', true], ['comptable', true], ['caissier', false]]);
