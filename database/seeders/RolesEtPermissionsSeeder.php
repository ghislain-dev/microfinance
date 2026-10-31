<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Organisation\Acces\Droit;
use App\Domain\Organisation\Acces\Role as RoleMetier;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles et permissions de référence. Idempotent : à relancer à chaque déploiement.
 */
class RolesEtPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Droit::cases() as $droit) {
            Permission::findOrCreate($droit->value, 'web');
        }
        // Le cache n'est pas vidé tout seul quand les événements de modèle sont coupés (WithoutModelEvents).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleMetier::cases() as $roleMetier) {
            // Rôle global (organisation_id NULL), utilisable dans chaque organisation.
            $role = Role::query()->firstOrCreate(['name' => $roleMetier->value, 'guard_name' => 'web', 'organisation_id' => null]);
            $role->syncPermissions(array_map(fn (Droit $droit) => $droit->value, $roleMetier->droits()));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
