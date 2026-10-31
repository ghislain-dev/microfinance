<?php

declare(strict_types=1);

use App\Domain\Caisse\Actions\OuvrirSession;
use App\Domain\Caisse\Billetage;
use App\Domain\Epargne\Actions\DeciderDemande;
use App\Domain\Epargne\Actions\Deposer;
use App\Domain\Epargne\Actions\FixerSeuil;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Epargne\Actions\Retirer;
use App\Domain\Epargne\Actions\Virer;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Exceptions\ValidationRequise;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\DemandeDecaissement;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\StatutDemande;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\Exceptions\SoldeInsuffisant;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Actions\ChangerStatutMembre;
use App\Domain\Membres\StatutMembre;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Epargnant;
use Tests\Support\GrandLivre;
use Tests\Support\Guichet;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

function usdE(string $m): Montant
{
    return Montant::depuisSaisie($m, Devise::USD);
}

function soldeEpargne(CompteEpargne $c): string
{
    return app(MoteurFinancier::class)->soldeReel($c->compte)->formate();
}

/**
 * Guichet ouvert (caissier en session) et un compte d'épargne USD.
 *
 * @param  array<string, mixed>  $parametres
 * @return array{Guichet, CompteEpargne}
 */
function guichetOuvert(array $parametres = []): array
{
    $g = Guichet::creer();
    $type = Epargnant::type($g->gerant, 'EPV', Devise::USD, $parametres);
    $compte = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), $type);
    actingAs($g->caissier);
    app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());

    return [$g, $compte];
}

function cle(): string
{
    return (string) Str::uuid();
}

/**
 * Lance une opération au-delà du seuil et renvoie la demande créée.
 */
function demandeCreee(Closure $operation): DemandeDecaissement
{
    try {
        $operation();
    } catch (ValidationRequise $e) {
        return $e->demande;
    }

    throw new RuntimeException('Aucune demande de validation créée');
}

it('dépose en espèces : caisse et épargne augmentent, mouvement lié à l’opération', function () {
    [$g, $compte] = guichetOuvert();

    $mouvement = app(Deposer::class)->executer($g->caissier, $compte, usdE('150'), cle());

    expect(soldeEpargne($compte))->toBe('150,00 USD')
        ->and(app(MoteurFinancier::class)->soldeReel($g->caisse->compte(Devise::USD))->formate())->toBe('150,00 USD')
        ->and($mouvement->type)->toBe('depot')
        ->and($mouvement->montant->formate())->toBe('150,00 USD')
        ->and($mouvement->operation_id)->not->toBeNull()
        ->and($mouvement->session_caisse_id)->toBe($g->caisse->sessionEnCours?->id);
});

it('est idempotent : deux envois de la même clé ne déposent qu’une fois', function () {
    [$g, $compte] = guichetOuvert();
    $cle = cle();

    app(Deposer::class)->executer($g->caissier, $compte, usdE('10'), $cle);
    app(Deposer::class)->executer($g->caissier, $compte, usdE('10'), $cle);

    expect(soldeEpargne($compte))->toBe('10,00 USD')->and(MouvementEpargne::count())->toBe(1);
});

it('exige une session de caisse ouverte par l’opérateur', function () {
    [$g, $compte] = guichetOuvert();
    actingAs($g->gerant);

    app(Deposer::class)->executer($g->gerant, $compte, usdE('10'), cle());
})->throws(CompteEpargneRefuse::class, 'session de caisse');

it('refuse un dépôt sous le minimum du produit, ou dans une autre devise', function () {
    [$g, $compte] = guichetOuvert(['depot_minimum' => '5']);

    expect(fn () => app(Deposer::class)->executer($g->caissier, $compte, usdE('4,99'), cle()))->toThrow(CompteEpargneRefuse::class)
        ->and(fn () => app(Deposer::class)->executer($g->caissier, $compte, Montant::depuisSaisie('10', Devise::CDF), cle()))->toThrow(CompteEpargneRefuse::class);
});

it('retire avec frais vers le produit et respecte le solde minimum', function () {
    [$g, $compte] = guichetOuvert(['solde_minimum' => '10', 'frais_retrait' => '0,50']);
    app(Deposer::class)->executer($g->caissier, $compte, usdE('100'), cle());

    $mouvement = app(Retirer::class)->executer($g->caissier, $compte, usdE('80'), cle());

    expect(soldeEpargne($compte))->toBe('19,50 USD')
        ->and($mouvement->frais->formate())->toBe('0,50 USD')
        ->and(app(MoteurFinancier::class)->soldeReel($g->caisse->compte(Devise::USD))->formate())->toBe('20,00 USD');
    // 19,50 - 9,01 - 0,50 = 9,99 < 10 de solde minimum
    expect(fn () => app(Retirer::class)->executer($g->caissier, $compte, usdE('9,01'), cle()))->toThrow(CompteEpargneRefuse::class, 'solde minimum');
    app(Retirer::class)->executer($g->caissier, $compte, usdE('9'), cle());
    expect(soldeEpargne($compte))->toBe('10,00 USD');
});

it('refuse un retrait sur une épargne bloquée avant l’échéance', function () {
    [$g, $compte] = guichetOuvert(['duree_blocage_mois' => 3]);
    app(Deposer::class)->executer($g->caissier, $compte, usdE('100'), cle());

    expect(fn () => app(Retirer::class)->executer($g->caissier, $compte, usdE('1'), cle()))->toThrow(CompteEpargneRefuse::class, 'bloqué');

    travelTo(today()->addMonthsNoOverflow(3));
    expect(app(Retirer::class)->executer($g->caissier, $compte, usdE('1'), cle())->exists)->toBeTrue();
});

it('ne laisse jamais la caisse payer plus qu’elle ne contient', function () {
    [$g, $compte] = guichetOuvert();
    app(Deposer::class)->executer($g->caissier, $compte, usdE('50'), cle());
    // L'argent quitte la caisse par une autre sortie (charge) : le membre garde 50 USD d'épargne.
    $charge = GrandLivre::compte($g->gerant, NatureCompte::Charge);
    actingAs($g->gerant);
    app(MoteurFinancier::class)->executer(cle(), 'charge', [
        LigneEcriture::debit($charge, usdE('45')),
        LigneEcriture::credit($g->caisse->compte(Devise::USD), usdE('45')),
    ]);
    actingAs($g->caissier);

    expect(fn () => app(Retirer::class)->executer($g->caissier, $compte, usdE('10'), cle()))->toThrow(SoldeInsuffisant::class)
        ->and(soldeEpargne($compte))->toBe('50,00 USD');
});

it('transforme un retrait au-delà du seuil en demande validée par un tiers, puis payée au guichet', function () {
    [$g, $compte] = guichetOuvert();
    app(FixerSeuil::class)->executer($g->gerant, Devise::USD, usdE('500'));
    app(Deposer::class)->executer($g->caissier, $compte, usdE('1000'), cle());

    try {
        app(Retirer::class)->executer($g->caissier, $compte, usdE('600'), cle());
        throw new RuntimeException('Gros retrait accepté sans validation');
    } catch (ValidationRequise $e) {
        $demande = $e->demande;
    }
    expect($demande->statut)->toBe(StatutDemande::EnAttente)->and(soldeEpargne($compte))->toBe('1 000,00 USD');

    expect(fn () => app(DeciderDemande::class)->approuver($g->caissier, $demande, 'moi'))->toThrow(AuthorizationException::class);
    $comptable = Acteurs::avecRole('comptable', $g->organisation);
    app(DeciderDemande::class)->approuver($comptable, $demande, 'Pièce vérifiée');
    actingAs($g->caissier);

    $mouvement = app(Retirer::class)->executer($g->caissier, $compte, usdE('600'), cle(), $demande->fresh());

    expect(soldeEpargne($compte))->toBe('400,00 USD')
        ->and($demande->fresh()?->statut)->toBe(StatutDemande::Executee)
        ->and($demande->fresh()?->operation_id)->toBe($mouvement->operation_id)
        ->and(fn () => app(Retirer::class)->executer($g->caissier, $compte, usdE('600'), cle(), $demande->fresh()))->toThrow(CompteEpargneRefuse::class);
});

it('n’exige pas de validation jusqu’au seuil inclus', function () {
    [$g, $compte] = guichetOuvert();
    app(FixerSeuil::class)->executer($g->gerant, Devise::USD, usdE('500'));
    app(Deposer::class)->executer($g->caissier, $compte, usdE('1000'), cle());

    expect(app(Retirer::class)->executer($g->caissier, $compte, usdE('500'), cle())->exists)->toBeTrue();
});

it('rejette une demande : rien n’est payé', function () {
    [$g, $compte] = guichetOuvert();
    app(FixerSeuil::class)->executer($g->gerant, Devise::USD, usdE('10'));
    app(Deposer::class)->executer($g->caissier, $compte, usdE('100'), cle());
    $demande = demandeCreee(fn () => app(Retirer::class)->executer($g->caissier, $compte, usdE('50'), cle()));

    app(DeciderDemande::class)->rejeter($g->gerant, $demande, 'Signature douteuse');

    expect($demande->fresh()?->statut)->toBe(StatutDemande::Rejetee);
    expect(fn () => app(Retirer::class)->executer($g->caissier, $compte, usdE('50'), cle(), $demande->fresh()))->toThrow(CompteEpargneRefuse::class);
});

it('vire entre deux membres de même devise, sans frais', function () {
    [$g, $source] = guichetOuvert(['frais_retrait' => '1']);
    app(Deposer::class)->executer($g->caissier, $source, usdE('100'), cle());
    $destination = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), $source->type);

    app(Virer::class)->executer($g->caissier, $source, $destination, usdE('30'), cle());

    expect(soldeEpargne($source))->toBe('70,00 USD')->and(soldeEpargne($destination))->toBe('30,00 USD')
        ->and(MouvementEpargne::where('type', 'virement_sortant')->sole()->operation_id)
        ->toBe(MouvementEpargne::where('type', 'virement_entrant')->sole()->operation_id);
});

it('exécute un gros virement dès sa validation', function () {
    [$g, $source] = guichetOuvert();
    app(FixerSeuil::class)->executer($g->gerant, Devise::USD, usdE('50'));
    app(Deposer::class)->executer($g->caissier, $source, usdE('100'), cle());
    $destination = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), $source->type);
    $demande = demandeCreee(fn () => app(Virer::class)->executer($g->caissier, $source, $destination, usdE('80'), cle()));

    app(DeciderDemande::class)->approuver($g->gerant, $demande, 'Accord du comité');

    expect(soldeEpargne($source))->toBe('20,00 USD')->and(soldeEpargne($destination))->toBe('80,00 USD')
        ->and($demande->fresh()?->statut)->toBe(StatutDemande::Executee);
});

it('refuse les virements entre devises, vers le même compte ou sans le droit', function () {
    [$g, $source] = guichetOuvert();
    app(Deposer::class)->executer($g->caissier, $source, usdE('100'), cle());
    $cdf = app(OuvrirCompteEpargne::class)->executer($g->gerant, $source->membre, Epargnant::type($g->gerant, 'EPC', Devise::CDF));

    expect(fn () => app(Virer::class)->executer($g->caissier, $source, $cdf, usdE('1'), cle()))->toThrow(CompteEpargneRefuse::class)
        ->and(fn () => app(Virer::class)->executer($g->caissier, $source, $source, usdE('1'), cle()))->toThrow(CompteEpargneRefuse::class)
        ->and(fn () => app(Virer::class)->executer(Acteurs::avecRole('comptable', $g->organisation), $source, $source, usdE('1'), cle()))->toThrow(AuthorizationException::class);
});

it('membre non actif : retrait possible, ni dépôt ni virement', function () {
    [$g, $compte] = guichetOuvert();
    app(Deposer::class)->executer($g->caissier, $compte, usdE('100'), cle());
    $autre = app(OuvrirCompteEpargne::class)->executer($g->gerant, Epargnant::membre($g), $compte->type);
    app(ChangerStatutMembre::class)->executer($g->gerant, $compte->membre, StatutMembre::Decede);
    actingAs($g->caissier);
    $compte = $compte->fresh(['membre', 'type', 'compte']) ?? throw new RuntimeException('Compte disparu');

    expect(fn () => app(Deposer::class)->executer($g->caissier, $compte, usdE('1'), cle()))->toThrow(CompteEpargneRefuse::class)
        ->and(fn () => app(Virer::class)->executer($g->caissier, $compte, $autre, usdE('1'), cle()))->toThrow(CompteEpargneRefuse::class)
        ->and(fn () => app(Virer::class)->executer($g->caissier, $autre, $compte, usdE('1'), cle()))->toThrow(CompteEpargneRefuse::class);

    app(Retirer::class)->executer($g->caissier, $compte, usdE('100'), cle()); // restitution
    expect(soldeEpargne($compte))->toBe('0,00 USD');
});

it('réserve le seuil à l’admin et au gérant', function () {
    $g = Guichet::creer();

    app(FixerSeuil::class)->executer($g->gerant, Devise::CDF, Montant::depuisSaisie('1000000', Devise::CDF));
    expect(DemandeDecaissement::count())->toBe(0);
    expect(fn () => app(FixerSeuil::class)->executer($g->caissier, Devise::USD, usdE('1')))->toThrow(AuthorizationException::class);
});
