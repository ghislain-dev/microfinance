<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Actions;

use App\Domain\Collecte\CollecteRefusee;
use App\Domain\Collecte\Models\ParametresCollecte;
use App\Domain\Collecte\Models\PlafondCollecte;
use App\Domain\Collecte\Models\Tournee;
use App\Domain\Epargne\Models\SeuilValidation;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Support\Facades\DB;

/**
 * Délai maximal sans synchronisation et plafonds par devise (ADR 022). Le plafond de retrait hors-ligne ne
 * dépasse jamais le seuil de validation 4 yeux : au-delà, le membre passe au guichet.
 */
final class FixerParametresCollecte
{
    /**
     * @param  array<string, array{plafond_retrait: string, plafond_especes: string}>  $plafonds  par code de devise
     */
    public function executer(User $gerant, int $delaiMaxHeures, array $plafonds): void
    {
        AutoriserDansOrganisation::autoriser($gerant, 'gerer', Tournee::class);
        if ($delaiMaxHeures < 1 || $delaiMaxHeures > 720) {
            throw new CollecteRefusee('Délai sans synchronisation : de 1 à 720 heures.');
        }

        $lignes = [];
        foreach ($plafonds as $code => $valeurs) {
            $devise = Devise::from($code);
            $retrait = Montant::depuisSaisie($valeurs['plafond_retrait'], $devise);
            $especes = Montant::depuisSaisie($valeurs['plafond_especes'], $devise);
            $seuil = SeuilValidation::query()->where('devise', $devise->value)->first();
            if ($seuil !== null && $retrait->comparer($seuil->montant) > 0) {
                throw new CollecteRefusee("Plafond de retrait {$devise->value} au-delà du seuil de validation ({$seuil->montant->formate()}).");
            }
            $lignes[] = [$devise, $retrait, $especes];
        }

        DB::transaction(function () use ($gerant, $delaiMaxHeures, $lignes) {
            $parametres = ParametresCollecte::query()->first() ?? new ParametresCollecte;
            $parametres->forceFill(['organisation_id' => $gerant->organisation_id, 'delai_max_heures' => $delaiMaxHeures])->save();
            foreach ($lignes as [$devise, $retrait, $especes]) {
                $plafond = PlafondCollecte::query()->where('devise', $devise->value)->first() ?? new PlafondCollecte;
                $plafond->forceFill(['organisation_id' => $gerant->organisation_id, 'devise' => $devise,
                    'plafond_retrait' => $retrait, 'plafond_especes' => $especes])->save();
            }
        });
    }
}
