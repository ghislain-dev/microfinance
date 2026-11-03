<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Actions;

use App\Domain\Collecte\Models\Tournee;
use App\Domain\Membres\Models\Membre;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;

/**
 * Retire un membre de la tournée : la ligne est datée (et non supprimée) pour que le téléphone l'apprenne.
 */
final class RetirerMembre
{
    public function executer(User $gerant, User $collecteur, Membre $membre): void
    {
        AutoriserDansOrganisation::autoriser($gerant, 'gerer', Tournee::class);
        Tournee::query()->where('collecteur_id', $collecteur->id)->where('membre_id', $membre->id)->whereNull('retire_le')
            ->first()?->forceFill(['retire_le' => now()])->save();
    }
}
