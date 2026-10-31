<?php

declare(strict_types=1);

use App\Domain\Caisse\Actions\CloturerSession;
use App\Domain\Caisse\Actions\CreerCaisse;
use App\Domain\Caisse\Actions\OuvrirSession;
use App\Domain\Caisse\Actions\ValiderEcart;
use App\Domain\Caisse\Billetage;
use App\Domain\Caisse\Exceptions\SessionDejaOuverte;
use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Caisse\StatutSession;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Organisation\Models\Agence;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Acteurs;
use Tests\Support\GrandLivre;
use Tests\Support\Guichet;

use function Pest\Laravel\actingAs;

function soldeCaisse(Guichet $g, Devise $devise): int
{
    return app(MoteurFinancier::class)->soldeReel($g->caisse->compte($devise))->centimes;
}

/**
 * Met de l'argent en caisse par une opération quelconque (contrepartie : un compte de produit).
 */
function alimenterCaisse(Guichet $g, string $montant, Devise $devise = Devise::USD): void
{
    $produit = GrandLivre::compte($g->gerant, NatureCompte::Produit, $devise);
    $m = Montant::depuisSaisie($montant, $devise);
    actingAs($g->gerant);
    app(MoteurFinancier::class)->executer('alim-'.uniqid('', true), 'essai', [LigneEcriture::debit($g->caisse->compte($devise), $m), LigneEcriture::credit($produit, $m)]);
    actingAs($g->caissier);
}

it('crée une caisse avec un compte de caisse par devise', function () {
    $g = Guichet::creer();

    expect($g->caisse->compte(Devise::USD)->nature)->toBe(NatureCompte::Caisse)
        ->and($g->caisse->compte(Devise::CDF)->devise)->toBe(Devise::CDF)
        ->and($g->caisse->compte(Devise::USD)->agence_id)->toBe($g->agence->id)
        ->and(Compte::where('nature', 'caisse')->count())->toBe(2);
});

it('réserve la création de caisse à l’admin et au gérant de l’organisation', function () {
    $g = Guichet::creer();

    expect(fn () => app(CreerCaisse::class)->executer($g->caissier, $g->agence, 'G2', 'Guichet 2'))->toThrow(AuthorizationException::class);
    auth()->forgetUser();
    $adminAilleurs = Acteurs::avecRole('admin');
    expect(fn () => app(CreerCaisse::class)->executer($adminAilleurs, $g->agence, 'G3', 'Guichet 3'))->toThrow(AuthorizationException::class);
});

it('calcule le billetage en entiers avec les seuls billets autorisés', function () {
    $b = Billetage::depuisSaisie(['USD' => ['100' => 3, '50' => 1, '1' => 0], 'CDF' => ['20000' => 2, '500' => 3]]);

    expect($b->total(Devise::USD)->formate())->toBe('350,00 USD')
        ->and($b->total(Devise::CDF)->formate())->toBe('41 500,00 CDF');
    expect(fn () => Billetage::depuisSaisie(['USD' => ['3' => 1]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Billetage::depuisSaisie(['CDF' => ['25' => 1]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Billetage::depuisSaisie(['USD' => ['100' => -1]]))->toThrow(InvalidArgumentException::class);
});

it('ouvre une session quand le comptage correspond au solde de la caisse', function () {
    $g = Guichet::creer();
    alimenterCaisse($g, '350');

    $session = app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::depuisSaisie(['USD' => ['100' => 3, '50' => 1]]));

    expect($session->statut)->toBe(StatutSession::Ouverte)
        ->and($session->caissier_id)->toBe($g->caissier->id)
        ->and($session->ecarts()->count())->toBe(0)
        ->and(DB::table('billetages')->where('session_caisse_id', $session->id)->count())->toBe(2);
});

it('bloque l’ouverture en cas d’écart jusqu’à la validation du gérant (excédent → produit)', function () {
    $g = Guichet::creer();

    $session = app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::depuisSaisie(['USD' => ['10' => 1]]));
    expect($session->statut)->toBe(StatutSession::OuvertureAValider)
        ->and($session->ecarts()->sole()->ecart->formate())->toBe('10,00 USD');

    actingAs($g->gerant);
    app(ValiderEcart::class)->executer($g->gerant, $session, 'Billet oublié dans le tiroir');

    expect($session->fresh()?->statut)->toBe(StatutSession::Ouverte)
        ->and($session->fresh()?->valide_par_id)->toBe($g->gerant->id)
        ->and(soldeCaisse($g, Devise::USD))->toBe(1000)
        ->and(Compte::where('nature', 'produit')->where('devise', 'USD')->sole()->solde_cache->centimes)->toBe(1000);
});

it('ferme une session sans écart et libère la caisse', function () {
    $g = Guichet::creer();
    $ouverture = app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());

    $fermee = app(CloturerSession::class)->executer($g->caissier, $ouverture, Billetage::vide());

    expect($fermee->statut)->toBe(StatutSession::Fermee)->and($fermee->fermee_le)->not->toBeNull();
    expect(app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide())->statut)->toBe(StatutSession::Ouverte);
});

it('met la session en écart à la clôture et impute un manquant au caissier', function () {
    $g = Guichet::creer();
    $session = app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());
    alimenterCaisse($g, '100');

    $session = app(CloturerSession::class)->executer($g->caissier, $session, Billetage::depuisSaisie(['USD' => ['50' => 1, '20' => 2], 'CDF' => []]));
    expect($session->statut)->toBe(StatutSession::EcartAJustifier)
        ->and($session->ecarts()->sole()->ecart->formate())->toBe('-10,00 USD');

    actingAs($g->gerant);
    app(ValiderEcart::class)->executer($g->gerant, $session, 'Manquant constaté, à rembourser');

    $creance = Compte::where('nature', 'creance_personnel')->sole();
    expect($session->fresh()?->statut)->toBe(StatutSession::Fermee)
        ->and(soldeCaisse($g, Devise::USD))->toBe(9000)
        ->and($creance->solde_cache->formate())->toBe('10,00 USD')
        ->and($creance->libelle)->toContain($g->caissier->name);
});

it('refuse que le caissier, un comptable ou un gérant valide un écart qu’il a lui-même produit', function () {
    $g = Guichet::creer();
    $session = app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::depuisSaisie(['USD' => ['1' => 1]]));

    expect(fn () => app(ValiderEcart::class)->executer($g->caissier, $session, 'moi'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ValiderEcart::class)->executer(Acteurs::avecRole('comptable', $g->organisation), $session, 'x'))->toThrow(AuthorizationException::class);

    // Le gérant tient lui-même la caisse : il ne valide pas son propre écart.
    auth()->forgetUser();
    $g2 = Guichet::creer();
    actingAs($g2->gerant);
    $sessionDuGerant = app(OuvrirSession::class)->executer($g2->gerant, $g2->caisse, Billetage::depuisSaisie(['USD' => ['1' => 1]]));
    expect(fn () => app(ValiderEcart::class)->executer($g2->gerant, $sessionDuGerant, 'moi'))->toThrow(AuthorizationException::class);
});

it('exige un motif pour valider un écart', function () {
    $g = Guichet::creer();
    $session = app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::depuisSaisie(['USD' => ['1' => 1]]));
    actingAs($g->gerant);

    app(ValiderEcart::class)->executer($g->gerant, $session, ' ');
})->throws(InvalidArgumentException::class);

it('refuse une deuxième session sur la même caisse, ou un caissier sur deux caisses', function () {
    $g = Guichet::creer();
    app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());

    expect(fn () => app(OuvrirSession::class)->executer($g->gerant, $g->caisse, Billetage::vide()))->toThrow(SessionDejaOuverte::class);

    actingAs($g->gerant);
    $autre = app(CreerCaisse::class)->executer($g->gerant, $g->agence, 'G2', 'Guichet 2');
    actingAs($g->caissier);
    expect(fn () => app(OuvrirSession::class)->executer($g->caissier, $autre, Billetage::vide()))->toThrow(SessionDejaOuverte::class);
});

it('bloque aussi en base deux sessions actives sur une caisse', function () {
    $g = Guichet::creer();
    $session = app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());

    $copie = (array) DB::table('sessions_caisse')->where('id', $session->id)->first(['organisation_id', 'caisse_id', 'statut', 'ouverte_le']);
    DB::table('sessions_caisse')->insert([...$copie, 'caissier_id' => $g->gerant->id]);
})->throws(UniqueConstraintViolationException::class);

it('refuse un opérateur non rattaché à l’agence ou sans le droit', function () {
    $g = Guichet::creer();
    $horsAgence = Acteurs::avecRole('caissier', $g->organisation);
    $collecteur = Acteurs::avecRole('collecteur', $g->organisation);
    $collecteur->agences()->attach($g->agence, ['organisation_id' => $g->organisation->id]);

    expect(fn () => app(OuvrirSession::class)->executer($horsAgence, $g->caisse, Billetage::vide()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(OuvrirSession::class)->executer($collecteur, $g->caisse, Billetage::vide()))->toThrow(AuthorizationException::class);
});

it('ne laisse clôturer une session que par son caissier', function () {
    $g = Guichet::creer();
    $session = app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());

    app(CloturerSession::class)->executer($g->gerant, $session, Billetage::vide());
})->throws(AuthorizationException::class);

it('garde le billetage en insertion seule', function () {
    $g = Guichet::creer();
    app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::depuisSaisie(['USD' => ['1' => 1]]));

    expect(fn () => DB::statement('update billetages set nombre = 0'))->toThrow(QueryException::class, 'insertion seule');
});

it('lit les sessions dans son organisation seulement', function () {
    $g = Guichet::creer();
    app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());
    auth()->forgetUser();
    actingAs(Acteurs::avecRole('gerant'));

    expect(SessionCaisse::count())->toBe(0)->and(Agence::count())->toBe(0);
});
