<?php

declare(strict_types=1);

// Fonctions d'aide des tests de tontine (chargées par tests/Pest.php).

use App\Domain\Caisse\Actions\OuvrirSession;
use App\Domain\Caisse\Billetage;
use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Finance\Actions\CloturerPeriode;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Models\Membre;
use App\Domain\Organisation\Models\Organisation;
use App\Domain\Tontines\Actions\DemarrerCycle;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Seance;
use App\Domain\Tontines\TypeGroupe;
use App\Support\Montant\Devise;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Tests\Support\Epargnant;
use Tests\Support\Guichet;
use Tests\Support\Tontine;

use function Pest\Laravel\actingAs;

/**
 * Cycle rotatif de 3 membres (10 000 CDF, FS 500, pénalités 1 000 / 500 / 2 000), démarré,
 * chaque membre avec un compte d'épargne CDF ; caisse ouverte par le caissier.
 *
 * @param  array<string, mixed>  $parametres
 * @param  list<int>|null  $ordre
 * @return array{Guichet, Cycle, list<Membre>, array<int, CompteEpargne>}
 */
function cycleEnCours(TypeGroupe $type = TypeGroupe::Rotative, array $parametres = [], ?array $ordre = null): array
{
    $g = Guichet::creer();
    [$groupe, $membres] = Tontine::groupe($g, $type, 3);
    $typeEpargne = Epargnant::type($g->gerant, 'EPC', Devise::CDF);
    $comptes = [];
    foreach ($membres as $m) {
        $comptes[$m->id] = app(OuvrirCompteEpargne::class)->executer($g->gerant, $m, $typeEpargne);
    }
    $cycle = Tontine::cycle($g, $groupe, $type === TypeGroupe::Avec ? ['nombre_seances' => 4, 'mode_tours' => null, 'montant_cotisation' => '1000', ...$parametres] : $parametres);
    app(DemarrerCycle::class)->executer($g->gerant, $cycle, $ordre === null ? null : array_map(fn (int $i) => $membres[$i]->id, $ordre));
    actingAs($g->caissier);
    app(OuvrirSession::class)->executer($g->caissier, $g->caisse, Billetage::vide());

    return [$g, $cycle->fresh() ?? $cycle, $membres, $comptes];
}

/**
 * Tout le monde présent et à jour.
 *
 * @param  list<Membre>  $membres
 * @param  array<int, array<string, mixed>>  $exceptions  membre_id => champs
 * @return array<int, array<string, mixed>>
 */
function feuille(array $membres, array $exceptions = [], string $cotisation = '10000'): array
{
    $lignes = [];
    foreach ($membres as $m) {
        $lignes[$m->id] = ['presence' => 'present', 'cotisation' => $cotisation, 'parts' => null, 'fonds_social' => '500', 'reglement' => '0', ...($exceptions[$m->id] ?? [])];
    }

    return $lignes;
}

function seance(Cycle $cycle, int $rang): Seance
{
    return Seance::where('cycle_id', $cycle->id)->where('rang', $rang)->sole();
}

function soldeCdf(CompteEpargne $c): string
{
    return app(MoteurFinancier::class)->soldeReel($c->compte)->formate();
}

/**
 * Clôture directe (action du moteur) : les tests du moteur n'ont pas à passer par le circuit comptable puis gérant.
 */
function cloturerJusquAu(Organisation $organisation, CarbonInterface $date): void
{
    app(CloturerPeriode::class)->executer($organisation, CarbonImmutable::parse($date->toDateString()), 'test');
}
