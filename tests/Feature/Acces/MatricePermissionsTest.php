<?php

declare(strict_types=1);

use Database\Seeders\RolesEtPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\Acteurs;

use function Pest\Laravel\seed;

// Matrice validée pour la Phase 0 (2026-10-31). Ce tableau est la spécification :
// il est écrit ici à la main, indépendamment du code qui l'implémente.
const MATRICE_PHASE_0 = [
    'tableau_de_bord.voir' => ['admin', 'gerant', 'comptable', 'caissier', 'collecteur', 'agent_credit', 'comite'],
    'organisation.modifier' => ['admin'],
    'agences.voir' => ['admin', 'gerant', 'comptable'],
    'agences.gerer' => ['admin'],
    'utilisateurs.voir' => ['admin', 'gerant'],
    'utilisateurs.gerer' => ['admin'],
    'audit.voir' => ['admin', 'gerant', 'comptable'],
    // Phase 1 (2026-10-31) : validation des extournes, pas par l'admin.
    'operations.extourner' => ['gerant', 'comptable'],
    // Phase 1 (2026-10-31) : membres.
    'membres.voir' => ['admin', 'gerant', 'comptable', 'caissier', 'collecteur', 'agent_credit', 'comite'],
    'membres.gerer' => ['admin', 'gerant', 'caissier'],
    // Phase 2 (2026-10-31) : caisses.
    'caisses.gerer' => ['admin', 'gerant'],
    'caisse.operer' => ['gerant', 'caissier'],
    'caisse.valider_ecart' => ['gerant'],
    // Phase 2 (2026-10-31) : épargne.
    'epargne.parametrer' => ['admin', 'gerant'],
    'epargne.ouvrir_compte' => ['admin', 'gerant', 'caissier'],
    'epargne.virer' => ['gerant', 'caissier'],
    'operations.valider_decaissement' => ['gerant', 'comptable'],
    'change.saisir_taux' => ['gerant', 'comptable'],
    'epargne.remuneration' => ['gerant', 'comptable'],
    // Phase 3 (2026-10-31) : tontines.
    'tontines.gerer' => ['admin', 'gerant'],
    'tontines.valider' => ['comite'],

    'credit.parametrer' => ['admin', 'gerant'],
    'credit.saisir' => ['gerant', 'agent_credit'],
    'credit.approuver_comite' => ['comite'],
    'credit.approuver_gerant' => ['gerant'],
    'credit.decaisser' => ['gerant', 'comptable'],

    'membres.acces_mobile' => ['gerant', 'caissier'],
    'collecte.gerer' => ['gerant'],
    'collecte.saisir' => ['collecteur'],
];

const ROLES = ['admin', 'gerant', 'comptable', 'caissier', 'collecteur', 'agent_credit', 'comite', 'membre'];

it('crée exactement les 8 rôles et les permissions de la matrice', function () {
    (new RolesEtPermissionsSeeder)->run();

    expect(Role::pluck('name')->sort()->values()->all())->toBe(collect(ROLES)->sort()->values()->all())
        ->and(Permission::pluck('name')->sort()->values()->all())->toBe(collect(MATRICE_PHASE_0)->keys()->sort()->values()->all());
});

it('peut être relancé sans créer de doublon', function () {
    (new RolesEtPermissionsSeeder)->run();
    (new RolesEtPermissionsSeeder)->run();

    expect(Role::count())->toBe(8)
        ->and(Permission::count())->toBe(count(MATRICE_PHASE_0));
});

it('applique la matrice', function (string $role, string $permission) {
    $utilisateur = Acteurs::avecRole($role);
    $attendu = in_array($role, MATRICE_PHASE_0[$permission], true);

    expect($utilisateur->can($permission))->toBe($attendu);
})->with(ROLES)->with(array_keys(MATRICE_PHASE_0));

it('ne donne aucun droit au membre (accès par l’application mobile uniquement)', function () {
    expect(Acteurs::avecRole('membre')->getAllPermissions())->toHaveCount(0);
});

it('ne compte pas un rôle attribué dans une autre organisation', function () {
    $admin = Acteurs::avecRole('admin');
    $autre = Acteurs::avecRole('caissier');

    // l'admin de A reçoit un rôle sous l'organisation B : il ne vaut rien chez A
    setPermissionsTeamId($autre->organisation_id);
    $admin->unsetRelation('roles')->assignRole('comptable');
    setPermissionsTeamId($admin->organisation_id);
    $admin->unsetRelation('roles')->unsetRelation('permissions');

    expect($admin->getRoleNames()->all())->toBe(['admin']);
});

it('fonctionne aussi via DatabaseSeeder (événements de modèle désactivés)', function () {
    seed();

    expect(Role::findByName('gerant')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect(MATRICE_PHASE_0)->filter(fn (array $roles) => in_array('gerant', $roles, true))->keys()->sort()->values()->all());
});
