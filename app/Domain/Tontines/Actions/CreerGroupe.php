<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Actions;

use App\Domain\Organisation\Models\Agence;
use App\Domain\Tontines\Models\Groupe;
use App\Domain\Tontines\TypeGroupe;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\MultiOrganisation\OrganisationIncoherente;

final class CreerGroupe
{
    public function executer(User $acteur, Agence $agence, string $code, string $nom, TypeGroupe $type): Groupe
    {
        AutoriserDansOrganisation::autoriser($acteur, 'create', Groupe::class);
        if ($agence->organisation_id !== $acteur->organisation_id) {
            throw new OrganisationIncoherente('L’agence appartient à une autre organisation.');
        }

        $groupe = new Groupe(['code' => $code, 'nom' => $nom, 'type' => $type]);
        $groupe->forceFill(['organisation_id' => $agence->organisation_id, 'agence_id' => $agence->id])->save();

        return $groupe->refresh();
    }
}
