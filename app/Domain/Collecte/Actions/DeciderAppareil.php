<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Actions;

use App\Domain\Collecte\Models\Appareil;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Le gérant autorise un téléphone de collecteur, ou le révoque (perdu, volé) : ses jetons sont alors supprimés.
 */
final class DeciderAppareil
{
    public function autoriser(User $gerant, Appareil $appareil): Appareil
    {
        AutoriserDansOrganisation::autoriser($gerant, 'decider', $appareil);
        $appareil->forceFill(['statut' => 'autorise', 'decide_par_id' => $gerant->id, 'autorise_le' => now(), 'revoque_le' => null])->save();

        return $appareil;
    }

    public function revoquer(User $gerant, Appareil $appareil): Appareil
    {
        AutoriserDansOrganisation::autoriser($gerant, 'decider', $appareil);

        return DB::transaction(function () use ($gerant, $appareil) {
            $appareil->forceFill(['statut' => 'revoque', 'decide_par_id' => $gerant->id, 'revoque_le' => now()])->save();
            PersonalAccessToken::query()->where('appareil_id', $appareil->id)->delete();

            return $appareil;
        });
    }
}
