<?php

declare(strict_types=1);

use App\Domain\Credit\Actions\DecaisserPret;
use App\Domain\Credit\Actions\RembourserPret;
use App\Domain\Credit\CreditRefuse;
use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\Remboursement;
use App\Domain\Credit\ModeRemboursement;
use App\Domain\Credit\Services\IndicateursCredit;
use App\Domain\Credit\StatutPret;
use App\Domain\Epargne\Actions\Retirer;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\Models\Ecriture;
use App\Domain\Finance\Models\Operation;
use App\Domain\Finance\MoteurFinancier;
use App\Support\Montant\Devise;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Tests\Support\Acteurs;
use Tests\Support\Credit;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

/**
 * Prêt de 1 000 USD décaissé le 15/01/2026 : 10 mensualités linéaires de 100 de capital, 3 % par mois
 * (intérêts 30, 27, 24… soit 165 au total), garantie 200, pénalité 0,5 % par jour après 3 jours de grâce.
 *
 * @return array{Credit, Pret}
 */
function pretEnCours(): array
{
    travelTo('2026-01-15 09:00');
    $c = Credit::preparer();
    $pret = $c->approuve('1000', 10);
    app(DecaisserPret::class)->executer($c->comptable, $pret);

    return [$c, $pret->refresh()];
}

function rembourser(Credit $c, Pret $pret, string $montant, ModeRemboursement $mode = ModeRemboursement::Especes, ?string $cle = null): Remboursement
{
    return app(RembourserPret::class)->executer($c->g->caissier, $pret, Credit::usd($montant), $mode, $cle ?? (string) Str::uuid());
}

function echeance(Pret $pret, int $rang): EcheancePret
{
    return EcheancePret::where('pret_id', $pret->id)->where('rang', $rang)->sole();
}

function soldeDuCompte(?Compte $compte): string
{
    return $compte === null ? '—' : app(MoteurFinancier::class)->soldeReel($compte)->formate();
}

function constaterPenalites(Credit $c): void
{
    auth()->forgetUser(); // commande planifiée : aucun utilisateur connecté
    artisan('credit:constater-penalites')->assertSuccessful();
    auth()->setUser($c->g->caissier);
}

it('rembourse une échéance en espèces : intérêts puis capital, caisse créditée', function () {
    [$c, $pret] = pretEnCours();
    travelTo('2026-02-15 10:00');

    $r = rembourser($c, $pret, '130');

    $e = echeance($pret, 1);
    expect($r->interets->formate())->toBe('30,00 USD')
        ->and($r->capital->formate())->toBe('100,00 USD')
        ->and($r->penalites->formate())->toBe('0,00 USD')
        ->and($e->payee_le)->not->toBeNull()
        ->and(soldeDuCompte($pret->comptePret))->toBe('900,00 USD')
        ->and(soldeDuCompte(Compte::where('numero', 'PRODUIT-INTERETS-PRET-USD')->first()))->toBe('30,00 USD')
        ->and(soldeDuCompte($c->g->caisse->compte(Devise::USD)))->toBe('630,00 USD')
        ->and((int) Ecriture::sum('debit'))->toBe((int) Ecriture::sum('credit'));
});

it('constate les pénalités chaque jour après le délai de grâce, sur le capital impayé, une seule fois par jour', function () {
    [$c, $pret] = pretEnCours();

    travelTo('2026-02-18 23:00'); // échéance du 15 + 3 jours de grâce : pas encore en retard
    constaterPenalites($c);
    expect(echeance($pret, 1)->penalite->formate())->toBe('0,00 USD');

    travelTo('2026-02-19 01:00');
    constaterPenalites($c);
    constaterPenalites($c);
    expect(echeance($pret, 1)->penalite->formate())->toBe('0,50 USD'); // 100 × 0,5 %

    travelTo('2026-02-22 01:00'); // commande manquée trois nuits : rattrapage du 20 au 22
    constaterPenalites($c);
    expect(echeance($pret, 1)->penalite->formate())->toBe('2,00 USD')
        ->and(soldeDuCompte($pret->comptePenalites))->toBe('2,00 USD')
        ->and(soldeDuCompte(Compte::where('numero', 'PRODUIT-PENALITES-PRET-USD')->first()))->toBe('2,00 USD')
        ->and(Operation::where('type', 'penalite_credit')->count())->toBe(4);
});

it('impute pénalités → intérêts → capital, échéance la plus ancienne d’abord ; plus de pénalité sur un capital payé', function () {
    [$c, $pret] = pretEnCours();
    travelTo('2026-02-20 01:00');
    constaterPenalites($c); // 19 et 20 : 1,00

    $r1 = rembourser($c, $pret, '20');
    expect([$r1->penalites->formate(), $r1->interets->formate(), $r1->capital->formate()])->toBe(['1,00 USD', '19,00 USD', '0,00 USD']);

    $r2 = rembourser($c, $pret, '111');
    expect([$r2->penalites->formate(), $r2->interets->formate(), $r2->capital->formate()])->toBe(['0,00 USD', '11,00 USD', '100,00 USD'])
        ->and(echeance($pret, 1)->payee_le)->not->toBeNull()
        ->and(echeance($pret, 2)->capital_paye->formate())->toBe('0,00 USD');

    travelTo('2026-02-25 01:00');
    constaterPenalites($c);
    expect(echeance($pret, 1)->penalite->formate())->toBe('1,00 USD');
});

it('rembourse depuis l’épargne avec reçu, sans toucher à la garantie bloquée', function () {
    [$c, $pret] = pretEnCours();
    travelTo('2026-02-15 10:00');

    rembourser($c, $pret, '130', ModeRemboursement::Epargne);
    expect(soldeDuCompte($c->compte->compte))->toBe('1 370,00 USD')
        ->and(MouvementEpargne::where('type', 'remboursement_credit')->sole()->recu)->not->toBeNull();

    app(Retirer::class)->executer($c->g->caissier, $c->compte, Credit::usd('400'), (string) Str::uuid()); // reste 970, dont 200 bloqués
    expect(fn () => rembourser($c, $pret, '800', ModeRemboursement::Epargne))->toThrow(CompteEpargneRefuse::class, 'garantie');
    rembourser($c, $pret, '770', ModeRemboursement::Epargne);
    expect(soldeDuCompte($c->compte->compte))->toBe('200,00 USD');
});

it('remboursement anticipé : capital restant et tous les intérêts prévus, prêt soldé, garantie levée', function () {
    [$c, $pret] = pretEnCours();
    travelTo('2026-02-01 10:00');

    expect(fn () => rembourser($c, $pret, '1165,01'))->toThrow(CreditRefuse::class);
    $r = rembourser($c, $pret, '1165');

    expect($r->interets->formate())->toBe('165,00 USD')
        ->and($r->capital->formate())->toBe('1 000,00 USD')
        ->and($pret->refresh()->statut)->toBe(StatutPret::Solde)
        ->and(soldeDuCompte($pret->comptePret))->toBe('0,00 USD')
        ->and(EcheancePret::where('pret_id', $pret->id)->whereNull('payee_le')->count())->toBe(0);

    app(Retirer::class)->executer($c->g->caissier, $c->compte, Credit::usd('1500'), (string) Str::uuid());
    expect(soldeDuCompte($c->compte->compte))->toBe('0,00 USD')
        ->and(fn () => rembourser($c, $pret, '1'))->toThrow(CreditRefuse::class);
});

it('est idempotent : la même clé ne rembourse qu’une fois', function () {
    [$c, $pret] = pretEnCours();
    $cle = (string) Str::uuid();

    for ($i = 0; $i < 10; $i++) {
        rembourser($c, $pret, '50', cle: $cle);
    }

    expect(Remboursement::count())->toBe(1)
        ->and(soldeDuCompte($pret->comptePret))->toBe('980,00 USD'); // 30 d'intérêts, 20 de capital
});

it('réservé au caissier et au gérant, session de caisse ouverte pour les espèces', function () {
    [$c, $pret] = pretEnCours();

    expect(fn () => app(RembourserPret::class)->executer($c->agent, $pret, Credit::usd('10'), ModeRemboursement::Especes, (string) Str::uuid()))
        ->toThrow(AuthorizationException::class);
    $caissier2 = Acteurs::avecRole('caissier', $c->g->organisation);
    expect(fn () => app(RembourserPret::class)->executer($caissier2, $pret, Credit::usd('10'), ModeRemboursement::Especes, (string) Str::uuid()))
        ->toThrow(CompteEpargneRefuse::class, 'session');
});

it('calcule le PAR 30 et le PAR 90 sur l’encours', function () {
    [$c, $pret] = pretEnCours();
    // Second prêt, à jour, pour un autre membre de la même organisation.
    $autre = Credit::autreEmprunteur($c);
    app(DecaisserPret::class)->executer($c->comptable, $autre->approuve('1000', 10));
    travelTo('2026-03-20 10:00'); // échéance du 15/02 du premier prêt impayée depuis 33 jours
    rembourser($autre, Pret::where('membre_id', $autre->membre->id)->sole(), '257'); // ses deux échéances échues

    $par30 = app(IndicateursCredit::class)->par(30, Devise::USD);
    $par90 = app(IndicateursCredit::class)->par(90, Devise::USD);
    expect($par30->encours->formate())->toBe('1 800,00 USD') // 1 000 + 800
        ->and($par30->aRisque->formate())->toBe('1 000,00 USD')
        ->and($par30->tauxPb)->toBe(5_556)                  // 55,56 %
        ->and($par90->aRisque->formate())->toBe('0,00 USD')
        ->and($par90->tauxPb)->toBe(0);
});
