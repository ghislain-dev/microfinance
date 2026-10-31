<?php

declare(strict_types=1);

namespace App\Http\Navigation;

use App\Domain\Organisation\Acces\Droit;
use App\Models\User;

/**
 * Menu principal : une entrée n'apparaît que si l'utilisateur a l'un des droits correspondants.
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
            [new EntreeMenu('Membres', 'membres.index'), Droit::MembresVoir],
            [new EntreeMenu('Caisses', 'caisses.index'), [Droit::CaissesGerer, Droit::CaisseOperer, Droit::CaisseValiderEcart]],
            [new EntreeMenu('Produits d’épargne', 'epargne.types.index'), Droit::EpargneParametrer],
            [new EntreeMenu('Agences', null), Droit::AgencesVoir],
            [new EntreeMenu('Utilisateurs', null), Droit::UtilisateursVoir],
            [new EntreeMenu('Journal d’audit', null), Droit::AuditVoir],
        ];

        return array_values(array_map(
            fn (array $entree) => $entree[0],
            array_filter($entrees, fn (array $entree) => $utilisateur->canAny(
                array_map(fn (Droit $droit) => $droit->value, is_array($entree[1]) ? $entree[1] : [$entree[1]]),
            )),
        ));
    }
}
