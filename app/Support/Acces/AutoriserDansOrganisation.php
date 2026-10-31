<?php

declare(strict_types=1);

namespace App\Support\Acces;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Autorise une action en évaluant les rôles de l'acteur dans SON organisation (spatie « teams »),
 * même hors requête web (console, tests, actions appelées par un autre utilisateur).
 */
final class AutoriserDansOrganisation
{
    public static function autoriser(User $acteur, string $capacite, mixed $arguments = []): void
    {
        $equipePrecedente = getPermissionsTeamId();
        setPermissionsTeamId($acteur->organisation_id);
        try {
            Gate::forUser($acteur)->authorize($capacite, $arguments);
        } finally {
            setPermissionsTeamId($equipePrecedente);
        }
    }
}
