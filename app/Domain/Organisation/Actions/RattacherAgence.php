<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Actions;

use App\Domain\Organisation\Models\Agence;
use App\Models\User;
use App\Support\MultiOrganisation\OrganisationIncoherente;

/**
 * Rattache un utilisateur à une agence de sa propre organisation (idempotent).
 * La base le garantit aussi : clés étrangères composées (id, organisation_id) sur agence_user.
 */
final class RattacherAgence
{
    public function executer(User $utilisateur, Agence $agence): void
    {
        if ($utilisateur->organisation_id !== $agence->organisation_id) {
            throw new OrganisationIncoherente('L’agence n’appartient pas à l’organisation de l’utilisateur.');
        }

        $utilisateur->agences()->syncWithoutDetaching([
            $agence->id => ['organisation_id' => $utilisateur->organisation_id],
        ]);
    }
}
