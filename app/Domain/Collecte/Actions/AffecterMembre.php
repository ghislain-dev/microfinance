<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Actions;

use App\Domain\Collecte\CollecteRefusee;
use App\Domain\Collecte\Models\Tournee;
use App\Domain\Collecte\Services\CaisseCollecteur;
use App\Domain\Membres\Models\Membre;
use App\Domain\Organisation\Acces\Role;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Support\Facades\DB;

/**
 * Le gérant ajoute un membre à la tournée d'un collecteur (la caisse du collecteur est ouverte au besoin).
 */
final class AffecterMembre
{
    public function __construct(private readonly CaisseCollecteur $caisses) {}

    public function executer(User $gerant, User $collecteur, Membre $membre): Tournee
    {
        AutoriserDansOrganisation::autoriser($gerant, 'gerer', Tournee::class);
        if ($collecteur->organisation_id !== $gerant->organisation_id || $membre->organisation_id !== $gerant->organisation_id) {
            throw new CollecteRefusee('Collecteur ou membre d’une autre organisation.');
        }
        setPermissionsTeamId($collecteur->organisation_id);
        if (! $collecteur->hasRole(Role::Collecteur->value)) {
            throw new CollecteRefusee("{$collecteur->name} n'est pas collecteur.");
        }

        return DB::transaction(function () use ($gerant, $collecteur, $membre) {
            $this->caisses->obtenir($collecteur, $membre);
            $tournee = Tournee::query()->where('collecteur_id', $collecteur->id)->where('membre_id', $membre->id)->first() ?? new Tournee;
            $tournee->forceFill([
                'organisation_id' => $membre->organisation_id, 'collecteur_id' => $collecteur->id, 'membre_id' => $membre->id,
                'affecte_par_id' => $gerant->id, 'retire_le' => null,
            ])->save();

            return $tournee;
        });
    }
}
