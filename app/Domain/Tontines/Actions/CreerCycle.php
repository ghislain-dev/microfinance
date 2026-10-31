<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Actions;

use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Groupe;
use App\Domain\Tontines\ParametresCycle;
use App\Domain\Tontines\StatutCycle;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Support\Facades\DB;

/**
 * Prépare un cycle (numéroté par groupe) ; ses paramètres restent modifiables jusqu'au démarrage.
 */
final class CreerCycle
{
    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(User $acteur, Groupe $groupe, array $donnees): Cycle
    {
        AutoriserDansOrganisation::autoriser($acteur, 'update', $groupe);

        return DB::transaction(function () use ($groupe, $donnees) {
            Groupe::query()->whereKey($groupe->id)->lockForUpdate()->firstOrFail();
            $cycle = new Cycle;
            $cycle->forceFill([
                'organisation_id' => $groupe->organisation_id,
                'groupe_id' => $groupe->id,
                'numero' => (int) $groupe->cycles()->max('numero') + 1,
                'statut' => StatutCycle::EnPreparation,
            ]);
            ParametresCycle::appliquer($cycle, $groupe->type, $donnees);
            $cycle->save();

            return $cycle->refresh();
        });
    }
}
