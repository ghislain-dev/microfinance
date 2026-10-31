<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Organisation\Acces\Role;
use App\Domain\Organisation\Models\Organisation;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Seul moyen de créer une organisation : pas de super-administrateur web (décision du 2026-10-31).
 */
class CreerOrganisation extends Command
{
    protected $signature = 'organisation:creer {code : Code unique, ex. COOPEC-GOMA} {nom : Nom de l’organisation}';

    protected $description = 'Crée une organisation et son premier administrateur';

    public function handle(): int
    {
        $organisation = ['code' => (string) $this->argument('code'), 'nom' => (string) $this->argument('nom')];

        $erreurs = Validator::make($organisation, [
            'code' => ['required', 'string', 'max:30', 'unique:organisations,code'],
            'nom' => ['required', 'string', 'max:255'],
        ])->errors();
        if ($erreurs->isNotEmpty()) {
            return $this->echec($erreurs->all());
        }

        $admin = [
            'name' => (string) $this->ask('Nom de l’administrateur'),
            'email' => (string) $this->ask('E-mail de l’administrateur'),
            'password' => (string) $this->secret('Mot de passe (10 caractères minimum)'),
            'password_confirmation' => (string) $this->secret('Confirmer le mot de passe'),
        ];

        $erreurs = Validator::make($admin, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ])->errors();
        if ($erreurs->isNotEmpty()) {
            return $this->echec($erreurs->all());
        }

        (new RolesEtPermissionsSeeder)->run();

        $utilisateur = DB::transaction(function () use ($organisation, $admin): User {
            $org = Organisation::create($organisation);
            $utilisateur = $org->utilisateurs()->create([
                'name' => $admin['name'],
                'email' => $admin['email'],
                'password' => $admin['password'],
            ]);

            setPermissionsTeamId($org->id);
            $utilisateur->assignRole(Role::Admin->value);

            return $utilisateur;
        });

        $this->info("Organisation « {$organisation['nom']} » créée ; administrateur : {$utilisateur->email}");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $erreurs
     */
    private function echec(array $erreurs): int
    {
        foreach ($erreurs as $erreur) {
            $this->error($erreur);
        }

        return self::FAILURE;
    }
}
