<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Actions\CreerCycle;
use App\Domain\Tontines\Actions\CreerGroupe;
use App\Domain\Tontines\Actions\InscrireAuGroupe;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Groupe;
use App\Domain\Tontines\TypeGroupe;

/**
 * Groupes et cycles prêts à l'emploi, sur un Guichet.
 */
final class Tontine
{
    /**
     * @return array{Groupe, list<Membre>}
     */
    public static function groupe(Guichet $g, TypeGroupe $type = TypeGroupe::Rotative, int $membres = 3, string $code = 'MAMANS'): array
    {
        $groupe = app(CreerGroupe::class)->executer($g->gerant, $g->agence, $code, "Groupe {$code}", $type);
        $liste = [];
        for ($i = 0; $i < $membres; $i++) {
            $liste[] = $membre = Epargnant::membre($g);
            app(InscrireAuGroupe::class)->executer($g->gerant, $groupe, $membre);
        }

        return [$groupe, $liste];
    }

    /**
     * @param  array<string, mixed>  $parametres
     */
    public static function cycle(Guichet $g, Groupe $groupe, array $parametres = []): Cycle
    {
        return app(CreerCycle::class)->executer($g->gerant, $groupe, [
            'devise' => 'CDF', 'periodicite' => 'hebdomadaire', 'date_debut' => today()->addDay()->toDateString(),
            'montant_cotisation' => '10000', 'cotisation_fonds_social' => '500',
            'penalite_absence' => '1000', 'penalite_retard' => '500', 'penalite_impaye' => '2000',
            'mode_tours' => 'tirage', 'nombre_seances' => null,
            ...$parametres,
        ]);
    }
}
