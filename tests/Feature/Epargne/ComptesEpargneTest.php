<?php

declare(strict_types=1);

use App\Domain\Epargne\Actions\EnregistrerTypeCompte;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Membres\Actions\ChangerStatutMembre;
use App\Domain\Membres\StatutMembre;
use App\Support\Montant\Devise;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\Support\Acteurs;
use Tests\Support\Epargnant;
use Tests\Support\Guichet;

use function Pest\Laravel\actingAs;

it('crée un type de compte avec ses paramètres en centimes', function () {
    $g = Guichet::creer();

    $type = Epargnant::type($g->gerant, 'DAT', Devise::CDF, [
        'solde_minimum' => '5 000', 'depot_minimum' => '1000,50', 'frais_retrait' => '500', 'duree_blocage_mois' => 6,
    ]);

    expect($type->devise)->toBe(Devise::CDF)
        ->and($type->solde_minimum->formate())->toBe('5 000,00 CDF')
        ->and($type->depot_minimum->centimes)->toBe(100050)
        ->and($type->frais_retrait->formate())->toBe('500,00 CDF')
        ->and($type->duree_blocage_mois)->toBe(6)
        ->and($type->actif)->toBeTrue();
});

it('réserve le paramétrage à l’admin et au gérant, code unique dans l’organisation', function () {
    $g = Guichet::creer();
    Epargnant::type($g->gerant, 'EPV');

    expect(fn () => Epargnant::type($g->caissier, 'EPX'))->toThrow(AuthorizationException::class)
        ->and(fn () => Epargnant::type($g->gerant, 'EPV'))->toThrow(UniqueConstraintViolationException::class);
});

it('modifie les paramètres d’un type mais jamais sa devise ni son code', function () {
    $g = Guichet::creer();
    $type = Epargnant::type($g->gerant, 'EPV');

    $modifie = app(EnregistrerTypeCompte::class)->modifier($g->gerant, $type, [
        'code' => 'AUTRE', 'devise' => 'CDF', 'libelle' => 'Épargne à vue', 'solde_minimum' => '10',
        'depot_minimum' => '1', 'frais_retrait' => '0,50', 'duree_blocage_mois' => null, 'actif' => false,
    ]);

    expect($modifie->code)->toBe('EPV')->and($modifie->devise)->toBe(Devise::USD)
        ->and($modifie->libelle)->toBe('Épargne à vue')
        ->and($modifie->solde_minimum->centimes)->toBe(1000)
        ->and($modifie->actif)->toBeFalse();
});

it('ouvre des comptes numérotés membre + type + rang, avec un compte de grand livre d’épargne', function () {
    $g = Guichet::creer();
    $membre = Epargnant::membre($g);
    $dat = Epargnant::type($g->gerant, 'DAT', Devise::USD, ['duree_blocage_mois' => 6]);
    $epv = Epargnant::type($g->gerant, 'EPV', Devise::CDF);
    $ouvrir = app(OuvrirCompteEpargne::class);

    $c1 = $ouvrir->executer($g->caissier, $membre, $dat);
    $c2 = $ouvrir->executer($g->caissier, $membre, $dat);
    $c3 = $ouvrir->executer($g->caissier, $membre, $epv);

    expect([$c1->numero, $c2->numero, $c3->numero])->toBe(["{$membre->numero}-DAT-1", "{$membre->numero}-DAT-2", "{$membre->numero}-EPV-1"])
        ->and($c1->compte->nature)->toBe(NatureCompte::Epargne)
        ->and($c1->compte->devise)->toBe(Devise::USD)
        ->and($c3->compte->devise)->toBe(Devise::CDF)
        ->and($c1->compte->agence_id)->toBe($membre->agence_id)
        ->and($c1->compte->numero)->toBe($c1->numero)
        ->and($c1->date_ouverture->toDateString())->toBe(today()->toDateString())
        ->and($c1->bloque_jusqu_au?->toDateString())->toBe(today()->addMonthsNoOverflow(6)->toDateString())
        ->and($c3->bloque_jusqu_au)->toBeNull();
});

it('refuse d’ouvrir un compte à un membre non actif ou sur un type inactif', function () {
    $g = Guichet::creer();
    $membre = Epargnant::membre($g);
    $type = Epargnant::type($g->gerant);
    app(ChangerStatutMembre::class)->executer($g->gerant, $membre, StatutMembre::Suspendu);

    expect(fn () => app(OuvrirCompteEpargne::class)->executer($g->caissier, $membre, $type))->toThrow(DomainException::class);

    $actif = Epargnant::membre($g);
    $inactif = app(EnregistrerTypeCompte::class)->modifier($g->gerant, Epargnant::type($g->gerant, 'OLD'), [
        'libelle' => 'Ancien', 'solde_minimum' => '0', 'depot_minimum' => '0', 'frais_retrait' => '0', 'actif' => false,
    ]);
    expect(fn () => app(OuvrirCompteEpargne::class)->executer($g->caissier, $actif, $inactif))->toThrow(DomainException::class);
});

it('réserve l’ouverture à l’admin, au gérant et au caissier de l’organisation', function () {
    $g = Guichet::creer();
    $membre = Epargnant::membre($g);
    $type = Epargnant::type($g->gerant);

    expect(fn () => app(OuvrirCompteEpargne::class)->executer(Acteurs::avecRole('collecteur', $g->organisation), $membre, $type))
        ->toThrow(AuthorizationException::class);
    auth()->forgetUser();
    expect(fn () => app(OuvrirCompteEpargne::class)->executer(Acteurs::avecRole('caissier'), $membre, $type))
        ->toThrow(AuthorizationException::class);
});

it('n’accepte pas un type d’une autre organisation', function () {
    $g = Guichet::creer();
    $membre = Epargnant::membre($g);
    auth()->forgetUser();
    $ailleurs = Acteurs::avecRole('gerant');
    actingAs($ailleurs);
    $typeAilleurs = Epargnant::type($ailleurs, 'EPV');
    actingAs($g->caissier);

    app(OuvrirCompteEpargne::class)->executer($g->caissier, $membre, $typeAilleurs);
})->throws(AuthorizationException::class);
