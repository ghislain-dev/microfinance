<?php

declare(strict_types=1);

namespace App\Http\Navigation;

use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

/**
 * Menu de gauche : une entrée n'apparaît que si l'utilisateur a le droit correspondant.
 */
final class MenuPrincipal
{
    /**
     * @return list<EntreeMenu>
     */
    public static function pour(User $utilisateur): array
    {
        $entrees = [
            [new EntreeMenu('Tableau de bord', 'tableau-de-bord'), Droit::TableauDeBordVoir],
            [new EntreeMenu('Agences', null), Droit::AgencesVoir],
            [new EntreeMenu('Utilisateurs', null), Droit::UtilisateursVoir],
            [new EntreeMenu('Journal d’audit', null), Droit::AuditVoir],
        ];

        return array_values(array_map(
            fn (array $entree) => $entree[0],
            array_filter($entrees, fn (array $entree) => $utilisateur->can($entree[1]->value)),
        ));
    }
}
