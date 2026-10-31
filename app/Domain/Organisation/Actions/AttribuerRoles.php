<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Actions;

use App\Domain\Organisation\Acces\Role;
use App\Models\User;
use App\Support\Audit\JournalAudit;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Remplace les rôles d'un utilisateur de la même organisation que l'acteur.
 */
final class AttribuerRoles
{
    public function __construct(private readonly JournalAudit $journal = new JournalAudit) {}

    /**
     * @param  list<string>  $roles
     */
    public function executer(User $acteur, User $cible, array $roles): void
    {
        // Droits évalués dans l'organisation de l'acteur ; la Policy exige que la cible y soit aussi.
        $equipePrecedente = getPermissionsTeamId();
        setPermissionsTeamId($acteur->organisation_id);
        try {
            Gate::forUser($acteur)->authorize('modifierRoles', $cible);

            foreach ($roles as $role) {
                if (Role::tryFrom($role) === null) {
                    throw new InvalidArgumentException("Rôle inconnu : {$role}");
                }
            }

            $avant = $cible->unsetRelation('roles')->getRoleNames()->sort()->values()->all();
            $cible->syncRoles($roles);

            $this->journal->enregistrer('roles.modification', $cible,
                avant: ['roles' => $avant],
                apres: ['roles' => $cible->unsetRelation('roles')->getRoleNames()->sort()->values()->all()],
                acteur: $acteur,
            );
        } finally {
            setPermissionsTeamId($equipePrecedente);
        }
    }
}
