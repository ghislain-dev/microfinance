<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Organisation\Acces\Role;
use App\Domain\Organisation\Models\Agence;
use App\Domain\Organisation\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesEtPermissionsSeeder::class);

        // Données de démonstration (mot de passe connu) : jamais hors du poste de développement.
        if (! app()->environment('local')) {
            return;
        }

        $organisation = Organisation::factory()->create(['code' => 'DEMO', 'nom' => 'Coopérative de démonstration']);
        Agence::factory()->for($organisation)->create(['code' => 'SIEGE', 'nom' => 'Siège']);
        setPermissionsTeamId($organisation->id);

        foreach (Role::cases() as $role) {
            User::factory()->for($organisation)->create([
                'name' => ucfirst(str_replace('_', ' ', $role->value)).' démo',
                'email' => "{$role->value}@demo.test",
            ])->assignRole($role->value);
        }
    }
}
