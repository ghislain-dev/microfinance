<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Acces;

/**
 * Permissions (spatie/laravel-permission). Les permissions sur l'argent arrivent avec chaque phase.
 */
enum Droit: string
{
    case TableauDeBordVoir = 'tableau_de_bord.voir';
    case OrganisationModifier = 'organisation.modifier';
    case AgencesVoir = 'agences.voir';
    case AgencesGerer = 'agences.gerer';
    case UtilisateursVoir = 'utilisateurs.voir';
    case UtilisateursGerer = 'utilisateurs.gerer';
    case AuditVoir = 'audit.voir';
    case OperationsExtourner = 'operations.extourner';
    case MembresVoir = 'membres.voir';
    case MembresGerer = 'membres.gerer';
    case CaissesGerer = 'caisses.gerer';
    case CaisseOperer = 'caisse.operer';
    case CaisseValiderEcart = 'caisse.valider_ecart';
    case EpargneParametrer = 'epargne.parametrer';
    case EpargneOuvrirCompte = 'epargne.ouvrir_compte';
    case EpargneVirer = 'epargne.virer';
    case OperationsValiderDecaissement = 'operations.valider_decaissement';
}
