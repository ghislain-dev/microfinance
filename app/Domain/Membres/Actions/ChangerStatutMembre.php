<?php

declare(strict_types=1);

namespace App\Domain\Membres\Actions;

use App\Domain\Membres\Models\Membre;
use App\Domain\Membres\StatutMembre;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;

final class ChangerStatutMembre
{
    public function executer(User $acteur, Membre $membre, StatutMembre $statut): Membre
    {
        AutoriserDansOrganisation::autoriser($acteur, 'changerStatut', $membre);

        $membre->forceFill(['statut' => $statut])->save(); // journalisé (EstAudite)

        return $membre;
    }
}
