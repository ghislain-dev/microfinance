<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Organisation\Models\Organisation;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;
use Spatie\Permission\Models\Role;

/**
 * Création d'utilisateurs avec rôle pour les tests d'accès.
 */
final class Acteurs
{
    public static function avecRole(string $role, ?Organisation $organisation = null): User
    {
        if (! Role::where('name', $role)->exists()) {
            (new RolesEtPermissionsSeeder)->run();
        }

        $utilisateur = User::factory()->for($organisation ?? Organisation::factory())->create();
        setPermissionsTeamId($utilisateur->organisation_id);
        $utilisateur->assignRole($role);

        return $utilisateur;
    }
}
