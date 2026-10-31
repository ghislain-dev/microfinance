<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Actions;

use App\Domain\Membres\Models\Membre;
use App\Domain\Membres\StatutMembre;
use App\Domain\Tontines\Models\Groupe;
use App\Domain\Tontines\TontineRefusee;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;

/**
 * Inscrit un membre actif dans un groupe. Il participera aux cycles démarrés ensuite.
 */
final class InscrireAuGroupe
{
    public function executer(User $acteur, Groupe $groupe, Membre $membre): void
    {
        AutoriserDansOrganisation::autoriser($acteur, 'update', $groupe);
        if ($membre->organisation_id !== $groupe->organisation_id) {
            throw new TontineRefusee('Le membre appartient à une autre organisation.');
        }
        if ($membre->statut !== StatutMembre::Actif) {
            throw new TontineRefusee("Membre {$membre->statut->libelle()} : inscription impossible.");
        }
        if ($groupe->membres()->whereKey($membre->id)->exists()) {
            throw new TontineRefusee("{$membre->numero} est déjà dans le groupe {$groupe->code}.");
        }

        $groupe->membres()->attach($membre, ['organisation_id' => $groupe->organisation_id]);
    }
}
