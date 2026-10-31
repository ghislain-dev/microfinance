<?php

declare(strict_types=1);

use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Membres\Actions\ChangerStatutMembre;
use App\Domain\Membres\StatutMembre;
use App\Domain\Tontines\Actions\CreerGroupe;
use App\Domain\Tontines\Actions\DemarrerCycle;
use App\Domain\Tontines\Actions\InscrireAuGroupe;
use App\Domain\Tontines\Actions\ModifierCycle;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Groupe;
use App\Domain\Tontines\ModeTours;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\TontineRefusee;
use App\Domain\Tontines\TypeGroupe;
use App\Support\Montant\Devise;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\Support\Acteurs;
use Tests\Support\Epargnant;
use Tests\Support\Guichet;
use Tests\Support\Tontine;

use function Pest\Laravel\actingAs;

it('crée un groupe et y inscrit des membres actifs, une seule fois chacun', function () {
    $g = Guichet::creer();
    [$groupe, $membres] = Tontine::groupe($g, TypeGroupe::Avec, 2);

    expect($groupe->type)->toBe(TypeGroupe::Avec)
        ->and($groupe->membres()->pluck('membres.id')->sort()->values()->all())->toBe(collect($membres)->pluck('id')->sort()->values()->all())
        ->and(fn () => app(InscrireAuGroupe::class)->executer($g->gerant, $groupe, $membres[0]))->toThrow(TontineRefusee::class);

    $suspendu = Epargnant::membre($g);
    app(ChangerStatutMembre::class)->executer($g->gerant, $suspendu, StatutMembre::Suspendu);
    expect(fn () => app(InscrireAuGroupe::class)->executer($g->gerant, $groupe, $suspendu))->toThrow(TontineRefusee::class);
});

it('réserve la gestion des groupes à l’admin et au gérant, code unique', function () {
    $g = Guichet::creer();
    Tontine::groupe($g, TypeGroupe::Rotative, 0, 'G1');

    expect(fn () => Tontine::groupe($g, TypeGroupe::Rotative, 0, 'G1'))->toThrow(UniqueConstraintViolationException::class)
        ->and(fn () => app(CreerGroupe::class)->executer($g->caissier, $g->agence, 'G2', 'Groupe', TypeGroupe::Avec))
        ->toThrow(AuthorizationException::class);
});

it('crée un cycle en préparation avec ses paramètres en centimes', function () {
    $g = Guichet::creer();
    [$groupe] = Tontine::groupe($g);

    $cycle = Tontine::cycle($g, $groupe, ['mode_tours' => 'ordre_fixe']);

    expect($cycle->statut)->toBe(StatutCycle::EnPreparation)
        ->and($cycle->numero)->toBe(1)
        ->and($cycle->devise)->toBe(Devise::CDF)
        ->and($cycle->montant_cotisation->formate())->toBe('10 000,00 CDF')
        ->and($cycle->cotisation_fonds_social->formate())->toBe('500,00 CDF')
        ->and($cycle->penalite_impaye->formate())->toBe('2 000,00 CDF')
        ->and($cycle->mode_tours)->toBe(ModeTours::OrdreFixe);
});

it('exige un nombre de séances et 1 à 5 parts pour un cycle AVEC, pas de mode de tours', function () {
    $g = Guichet::creer();
    [$avec] = Tontine::groupe($g, TypeGroupe::Avec, 2);

    expect(fn () => Tontine::cycle($g, $avec, ['nombre_seances' => null]))->toThrow(InvalidArgumentException::class);

    $cycle = Tontine::cycle($g, $avec, ['nombre_seances' => 24, 'mode_tours' => null]);
    expect($cycle->nombre_seances)->toBe(24)->and($cycle->parts_min)->toBe(1)->and($cycle->parts_max)->toBe(5)->and($cycle->mode_tours)->toBeNull();
});

it('démarre un cycle : participants figés, comptes du cycle ouverts, séances = participants (rotative)', function () {
    $g = Guichet::creer();
    [$groupe, $membres] = Tontine::groupe($g, TypeGroupe::Rotative, 4);
    $cycle = Tontine::cycle($g, $groupe);

    app(DemarrerCycle::class)->executer($g->gerant, $cycle);

    $cycle = $cycle->fresh() ?? throw new RuntimeException;
    expect($cycle->statut)->toBe(StatutCycle::EnCours)
        ->and($cycle->nombre_seances)->toBe(4)
        ->and($cycle->participants()->count())->toBe(4)
        ->and($cycle->compteFonds->nature)->toBe(NatureCompte::CycleTontine)
        ->and($cycle->compteFondsSocial->nature)->toBe(NatureCompte::FondsSocial)
        ->and($cycle->compteCreances->nature)->toBe(NatureCompte::CreanceMembre)
        ->and($cycle->compteFonds->devise)->toBe(Devise::CDF);

    // Un membre inscrit après le démarrage ne participe pas à ce cycle.
    app(InscrireAuGroupe::class)->executer($g->gerant, $groupe, Epargnant::membre($g));
    expect($cycle->participants()->count())->toBe(4);
});

it('fige les paramètres d’un cycle démarré', function () {
    $g = Guichet::creer();
    [$groupe] = Tontine::groupe($g);
    $cycle = Tontine::cycle($g, $groupe);

    app(ModifierCycle::class)->executer($g->gerant, $cycle, ['montant_cotisation' => '12000']);
    expect($cycle->fresh()?->montant_cotisation->formate())->toBe('12 000,00 CDF');

    app(DemarrerCycle::class)->executer($g->gerant, $cycle);
    app(ModifierCycle::class)->executer($g->gerant, $cycle->fresh() ?? $cycle, ['montant_cotisation' => '1']);
})->throws(TontineRefusee::class);

it('refuse de démarrer un cycle sans au moins deux membres, ou un second cycle en cours', function () {
    $g = Guichet::creer();
    [$seul] = Tontine::groupe($g, TypeGroupe::Rotative, 1, 'SEUL');
    expect(fn () => app(DemarrerCycle::class)->executer($g->gerant, Tontine::cycle($g, $seul)))->toThrow(TontineRefusee::class);

    [$groupe] = Tontine::groupe($g, TypeGroupe::Rotative, 2, 'DUO');
    app(DemarrerCycle::class)->executer($g->gerant, Tontine::cycle($g, $groupe));
    $second = Tontine::cycle($g, $groupe);
    expect($second->numero)->toBe(2)
        ->and(fn () => app(DemarrerCycle::class)->executer($g->gerant, $second))->toThrow(TontineRefusee::class);
});

it('n’accepte qu’une devise, une périodicité et un mode connus', function (array $parametres) {
    $g = Guichet::creer();
    [$groupe] = Tontine::groupe($g);

    Tontine::cycle($g, $groupe, $parametres);
})->throws(ValueError::class)->with([
    [['devise' => 'EUR']],
    [['periodicite' => 'quotidienne']],
    [['mode_tours' => 'au_hasard']],
]);

it('isole les groupes par organisation', function () {
    $g = Guichet::creer();
    Tontine::groupe($g);
    auth()->forgetUser();
    actingAs(Acteurs::avecRole('gerant'));

    expect(Groupe::count())->toBe(0)->and(Cycle::count())->toBe(0);
});
