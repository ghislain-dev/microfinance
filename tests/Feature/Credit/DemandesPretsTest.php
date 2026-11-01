<?php

declare(strict_types=1);

use App\Domain\Credit\Actions\AnalyserPret;
use App\Domain\Credit\Actions\DecaisserPret;
use App\Domain\Credit\Actions\DeciderPret;
use App\Domain\Credit\Actions\DemanderPret;
use App\Domain\Credit\Actions\EnregistrerProduitCredit;
use App\Domain\Credit\Actions\FixerSeuilCredit;
use App\Domain\Credit\CreditRefuse;
use App\Domain\Credit\MethodeCalcul;
use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\Models\GarantiePret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\StatutPret;
use App\Domain\Epargne\Actions\Deposer;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Epargne\Actions\Retirer;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Finance\Models\Ecriture;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Actions\ChangerStatutMembre;
use App\Domain\Membres\StatutMembre;
use App\Support\Montant\Devise;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Credit;
use Tests\Support\Epargnant;
use Tests\Support\Guichet;

use function Pest\Laravel\travelTo;

function soldeCredit(Credit $c): string
{
    return app(MoteurFinancier::class)->soldeReel($c->compte->compte)->formate();
}

it('paramètre un produit de crédit (admin, gérant), jamais le caissier', function () {
    $c = Credit::preparer();

    expect($c->produit->taux_mensuel_pb)->toBe(300)
        ->and($c->produit->garantie_epargne_pb)->toBe(2_000)
        ->and($c->produit->penalite_jour_pb)->toBe(50)
        ->and($c->produit->montant_maximum->formate())->toBe('10 000,00 USD')
        ->and(fn () => Credit::produit($c->g->caissier, ['code' => 'AUT']))->toThrow(AuthorizationException::class);
});

it('enregistre une demande : paramètres du produit figés, statut « en analyse »', function () {
    $c = Credit::preparer();
    $pret = $c->demande('1000', 10, MethodeCalcul::Degressif, [['type' => 'bien', 'description' => 'Moto Haojue', 'valeur' => '800']]);
    app(EnregistrerProduitCredit::class)->modifier($c->g->gerant, $c->produit, [...$c->produit->only(['libelle']), 'taux_mensuel' => '4',
        'periodicite' => 'hebdomadaire', 'duree_max' => 24, 'montant_minimum' => '100', 'montant_maximum' => '10000', 'anciennete_mois' => 0,
        'multiple_epargne' => 3, 'garantie_epargne' => '20', 'penalite_jour' => '0,5', 'delai_grace_jours' => 3]);

    $pret->refresh();
    expect($pret->statut)->toBe(StatutPret::EnAnalyse)
        ->and($pret->numero)->toBe($c->membre->numero.'-CR1')
        ->and($pret->taux_mensuel_pb)->toBe(300)
        ->and($pret->periodicite->value)->toBe('mensuelle')
        ->and($pret->methode)->toBe(MethodeCalcul::Degressif)
        ->and($pret->montant->formate())->toBe('1 000,00 USD')
        ->and($pret->saisi_par_id)->toBe($c->agent->id)
        ->and(GarantiePret::where('pret_id', $pret->id)->sole()->description)->toBe('Moto Haojue');
});

it('refuse une demande hors des conditions du produit', function (array $produit, string $montant, int $nombre) {
    $c = Credit::preparer($produit);
    $c->demande($montant, $nombre);
})->throws(CreditRefuse::class)->with([
    'sous le minimum' => [[], '99,99', 10],
    'au-dessus du maximum' => [['multiple_epargne' => 100], '10000,01', 10],
    'au-delà de 3 × l’épargne' => [[], '1500,01', 10],
    'trop d’échéances' => [[], '1000', 25],
    'ancienneté insuffisante' => [['anciennete_mois' => 6], '1000', 10],
]);

it('accepte le plafond exact et l’ancienneté atteinte', function () {
    $c = Credit::preparer(['anciennete_mois' => 6]);
    travelTo(now()->addMonthsNoOverflow(6));

    expect($c->demande('1500')->statut)->toBe(StatutPret::EnAnalyse);
});

it('refuse un membre non actif et un compte qui n’est pas le sien ou pas dans la devise', function () {
    $c = Credit::preparer();
    $autre = app(OuvrirCompteEpargne::class)->executer($c->g->gerant, Epargnant::membre($c->g), $c->compte->type);
    $cdf = app(OuvrirCompteEpargne::class)->executer($c->g->gerant, $c->membre, Epargnant::type($c->g->gerant, 'EPC', Devise::CDF));
    $demander = fn ($compte) => app(DemanderPret::class)->executer($c->agent, $c->membre, $c->produit, $compte,
        Credit::usd('500'), 5, MethodeCalcul::Lineaire, 'Stock');

    expect(fn () => $demander($autre))->toThrow(CreditRefuse::class)
        ->and(fn () => $demander($cdf))->toThrow(CreditRefuse::class);

    app(ChangerStatutMembre::class)->executer($c->g->gerant, $c->membre, StatutMembre::Suspendu);
    expect(fn () => $demander($c->compte))->toThrow(CreditRefuse::class, 'Suspendu');
});

it('un seul prêt à la fois, garanti par la base ; nouvelle demande possible après un rejet', function () {
    $c = Credit::preparer();
    $pret = $c->demande();

    expect(fn () => $c->demande('200'))->toThrow(CreditRefuse::class, 'déjà');
    $brut = collect($pret->getAttributes())->except(['id', 'actif_flag', 'numero', 'rang'])->all();
    expect(fn () => DB::table('prets')->insert([...$brut, 'numero' => 'X', 'rang' => 9]))->toThrow(QueryException::class);

    app(AnalyserPret::class)->executer($c->agent, $pret, 'Revenus insuffisants.');
    app(DeciderPret::class)->rejeter($c->comite, $pret, 'Capacité insuffisante');
    expect($pret->refresh()->statut)->toBe(StatutPret::Rejete)
        ->and($c->demande('200')->numero)->toBe($c->membre->numero.'-CR2');
});

it('circuit : analyse obligatoire, comité puis gérant au-delà du seuil, jamais l’auteur ni deux fois la même personne', function () {
    $c = Credit::preparer();
    app(FixerSeuilCredit::class)->executer($c->g->gerant, Devise::USD, Credit::usd('500'));
    $pret = $c->demande(auteur: $c->g->gerant);

    expect(fn () => app(DeciderPret::class)->approuver($c->comite, $pret, 'ok'))->toThrow(AuthorizationException::class); // pas encore analysée
    app(AnalyserPret::class)->executer($c->g->gerant, $pret, 'Bon dossier.');
    expect($pret->refresh()->statut)->toBe(StatutPret::Soumis)
        ->and(fn () => app(DeciderPret::class)->approuver($c->g->gerant, $pret, 'ok'))->toThrow(AuthorizationException::class);

    app(DeciderPret::class)->approuver($c->comite, $pret, 'Accordé par le comité');
    expect($pret->refresh()->statut)->toBe(StatutPret::AttenteGerant)
        ->and($pret->comite_par_id)->toBe($c->comite->id)
        ->and(fn () => app(DeciderPret::class)->approuver($c->g->gerant, $pret, 'moi'))->toThrow(AuthorizationException::class);

    $gerant2 = Acteurs::avecRole('gerant', $c->g->organisation);
    app(DeciderPret::class)->approuver($gerant2, $pret, 'Accordé');
    expect($pret->refresh()->statut)->toBe(StatutPret::Approuve)
        ->and($pret->gerant_par_id)->toBe($gerant2->id);
});

it('sous le seuil, l’approbation du comité suffit', function () {
    $c = Credit::preparer();
    app(FixerSeuilCredit::class)->executer($c->g->gerant, Devise::USD, Credit::usd('1000'));

    expect($c->approuve('1000')->statut)->toBe(StatutPret::Approuve);
});

it('décaisse sur l’épargne : compte de prêt, échéancier, garantie bloquée, reçu', function () {
    $c = Credit::preparer();
    // Espèces en caisse pour le retrait final (dépôt d'un autre membre).
    $autre = app(OuvrirCompteEpargne::class)->executer($c->g->gerant, Epargnant::membre($c->g), $c->compte->type);
    app(Deposer::class)->executer($c->g->caissier, $autre, Credit::usd('1000'), (string) Str::uuid());
    travelTo('2026-01-31 10:00');
    $pret = $c->approuve('1000', 10);

    expect(fn () => app(DecaisserPret::class)->executer($c->g->caissier, $pret))->toThrow(AuthorizationException::class);
    app(DecaisserPret::class)->executer($c->comptable, $pret);

    $pret->refresh();
    $echeances = EcheancePret::where('pret_id', $pret->id)->orderBy('rang')->get();
    expect($pret->statut)->toBe(StatutPret::EnCours)
        ->and($pret->decaisse_par_id)->toBe($c->comptable->id)
        ->and($pret->date_decaissement->toDateString())->toBe('2026-01-31')
        ->and(soldeCredit($c))->toBe('1 500,00 USD')
        ->and(app(MoteurFinancier::class)->soldeReel($pret->comptePret)->formate())->toBe('1 000,00 USD')
        ->and($echeances)->toHaveCount(10)
        ->and($echeances->sum(fn (EcheancePret $e) => $e->capital->centimes))->toBe(100_000)
        ->and($echeances->first()?->date_echeance->toDateString())->toBe('2026-02-28')
        ->and($echeances->first()?->interet->formate())->toBe('30,00 USD')
        ->and($pret->garantie_epargne->formate())->toBe('200,00 USD')
        ->and(MouvementEpargne::where('type', 'decaissement_credit')->sole()->montant->formate())->toBe('1 000,00 USD')
        ->and((int) Ecriture::sum('debit'))->toBe((int) Ecriture::sum('credit'));

    // 1 500 − 200 de garantie : 1 300 retirables.
    expect(fn () => app(Retirer::class)->executer($c->g->caissier, $c->compte, Credit::usd('1300,01'), (string) Str::uuid()))
        ->toThrow(CompteEpargneRefuse::class, 'garantie');
    app(Retirer::class)->executer($c->g->caissier, $c->compte, Credit::usd('1300'), (string) Str::uuid());
    expect(soldeCredit($c))->toBe('200,00 USD');

    expect(fn () => app(DecaisserPret::class)->executer($c->comptable, $pret))->toThrow(AuthorizationException::class);
});

it('ne décaisse pas le prêt saisi par le décaisseur lui-même', function () {
    $c = Credit::preparer();
    $pret = $c->demande(auteur: $c->g->gerant);
    app(AnalyserPret::class)->executer($c->g->gerant, $pret, 'Bon dossier.');
    app(DeciderPret::class)->approuver($c->comite, $pret, 'Accordé');

    expect(fn () => app(DecaisserPret::class)->executer($c->g->gerant, $pret))->toThrow(AuthorizationException::class);
    app(DecaisserPret::class)->executer($c->comptable, $pret);
    expect($pret->refresh()->statut)->toBe(StatutPret::EnCours);
});

it('revérifie au décaissement : garantie disponible et plafond selon l’épargne', function () {
    $c = Credit::preparer(['multiple_epargne' => 100]);
    $pret = $c->approuve('1000');
    app(Retirer::class)->executer($c->g->caissier, $c->compte, Credit::usd('350'), (string) Str::uuid()); // reste 150 < 200

    expect(fn () => app(DecaisserPret::class)->executer($c->comptable, $pret))->toThrow(CreditRefuse::class, 'garantie')
        ->and($pret->refresh()->statut)->toBe(StatutPret::Approuve)
        ->and(EcheancePret::count())->toBe(0);

    auth()->forgetUser();
    $c2 = Credit::preparer();
    $pret2 = $c2->approuve('1000');
    app(Retirer::class)->executer($c2->g->caissier, $c2->compte, Credit::usd('250'), (string) Str::uuid()); // 3 × 250 < 1 000
    expect(fn () => app(DecaisserPret::class)->executer($c2->comptable, $pret2))->toThrow(CreditRefuse::class, 'plafond');
});

it('enregistre les cautions d’autres membres, jamais l’emprunteur lui-même', function () {
    $c = Credit::preparer();
    $caution = Epargnant::membre($c->g);
    $pret = $c->demande(garanties: [['type' => 'caution', 'membre_id' => $caution->id, 'montant' => '300']]);

    $g = GarantiePret::where('pret_id', $pret->id)->sole();
    expect($g->type)->toBe('caution')->and($g->membre_id)->toBe($caution->id)->and($g->montant?->formate())->toBe('300,00 USD')
        ->and(fn () => $c->demande('200', garanties: [['type' => 'caution', 'membre_id' => $c->membre->id, 'montant' => '100']]))
        ->toThrow(CreditRefuse::class);
});

it('refuse la caution d’un membre d’une autre organisation', function () {
    $c = Credit::preparer();
    auth()->forgetUser();
    $etranger = Epargnant::membre(Guichet::creer())->id;
    auth()->setUser($c->g->caissier);

    expect(fn () => $c->demande(garanties: [['type' => 'caution', 'membre_id' => $etranger, 'montant' => '100']]))->toThrow(CreditRefuse::class)
        ->and(Pret::count())->toBe(0);
});

it('cache les prêts des autres organisations', function () {
    $c = Credit::preparer();
    $pret = $c->demande();
    auth()->forgetUser();
    $comiteAilleurs = Acteurs::avecRole('comite');
    auth()->setUser($comiteAilleurs);

    expect(Pret::find($pret->id))->toBeNull()
        ->and(fn () => app(DeciderPret::class)->approuver($comiteAilleurs, $pret, 'ok'))->toThrow(ModelNotFoundException::class);
});
