<?php

declare(strict_types=1);

namespace App\Domain\Membres\Actions;

use App\Domain\Membres\Models\Membre;
use App\Domain\Membres\Services\FicheMembre;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Support\Arr;

/**
 * Modifie la fiche ; le numéro et l'agence d'inscription ne changent pas.
 */
final class ModifierMembre
{
    public function __construct(private readonly FicheMembre $fiche) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(User $acteur, Membre $membre, array $donnees, bool $telephonePartageConfirme = false): Membre
    {
        AutoriserDansOrganisation::autoriser($acteur, 'update', $membre);

        // Un téléphone inchangé n'est pas un nouveau partage à confirmer.
        $attributs = $this->fiche->preparer($donnees, $telephonePartageConfirme, $membre);
        $membre->fill(Arr::except($attributs, 'piece_empreinte'));
        $membre->forceFill(['piece_empreinte' => $attributs['piece_empreinte']]);
        $membre->save();

        return $membre;
    }
}
