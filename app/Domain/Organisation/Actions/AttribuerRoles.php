<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Actions;

use App\Domain\Organisation\Acces\Role;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Audit\JournalAudit;
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
        // La Policy exige aussi que la cible soit dans l'organisation de l'acteur.
        AutoriserDansOrganisation::autoriser($acteur, 'modifierRoles', $cible);

        foreach ($roles as $role) {
            if (Role::tryFrom($role) === null) {
                throw new InvalidArgumentException("Rôle inconnu : {$role}");
            }
        }

        // Les rôles s'attribuent dans l'organisation de la cible (spatie « teams »).
        $equipePrecedente = getPermissionsTeamId();
        setPermissionsTeamId($cible->organisation_id);
        try {
            $avant = $cible->unsetRelation('roles')->getRoleNames()->sort()->values()->all();
            $cible->syncRoles($roles);
            $apres = $cible->unsetRelation('roles')->getRoleNames()->sort()->values()->all();
        } finally {
            setPermissionsTeamId($equipePrecedente);
        }

        $this->journal->enregistrer('roles.modification', $cible, avant: ['roles' => $avant], apres: ['roles' => $apres], acteur: $acteur);
    }
}
