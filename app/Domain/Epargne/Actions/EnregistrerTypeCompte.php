<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Actions;

use App\Domain\Epargne\Models\TypeCompteEpargne;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\Pourcentage;

/**
 * Création et modification des types de comptes d'épargne. Le code et la devise ne changent jamais.
 */
final class EnregistrerTypeCompte
{
    /**
     * @param  array<string, mixed>  $donnees  données validées
     */
    public function creer(User $acteur, array $donnees): TypeCompteEpargne
    {
        AutoriserDansOrganisation::autoriser($acteur, 'create', TypeCompteEpargne::class);
        $devise = Devise::from((string) $donnees['devise']);

        $type = new TypeCompteEpargne(['code' => (string) $donnees['code'], 'devise' => $devise]);
        $type->fill($this->parametres($donnees, $devise));
        $type->forceFill(['organisation_id' => $acteur->organisation_id])->save();

        return $type->refresh();
    }

    /**
     * @param  array<string, mixed>  $donnees  données validées (code et devise ignorés)
     */
    public function modifier(User $acteur, TypeCompteEpargne $type, array $donnees): TypeCompteEpargne
    {
        AutoriserDansOrganisation::autoriser($acteur, 'update', $type);

        $type->fill([...$this->parametres($donnees, $type->devise), 'actif' => (bool) ($donnees['actif'] ?? $type->actif)]);
        $type->save();

        return $type;
    }

    /**
     * « 3,25 » (%) → 325 points de base ; deux décimales au plus.
     */
    public static function pointsDeBase(string $saisie): int
    {
        return Pourcentage::pointsDeBase($saisie, "Taux d'intérêt");
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function parametres(array $donnees, Devise $devise): array
    {
        $duree = $donnees['duree_blocage_mois'] ?? null;

        return [
            'libelle' => trim((string) $donnees['libelle']),
            'solde_minimum' => Montant::depuisSaisie((string) $donnees['solde_minimum'], $devise),
            'depot_minimum' => Montant::depuisSaisie((string) $donnees['depot_minimum'], $devise),
            'frais_retrait' => Montant::depuisSaisie((string) $donnees['frais_retrait'], $devise),
            'duree_blocage_mois' => $duree === null || $duree === '' ? null : (int) $duree,
            // Absent du formulaire : le taux en place est conservé.
            ...(array_key_exists('taux_interet_annuel', $donnees)
                ? ['taux_interet_pb' => self::pointsDeBase((string) $donnees['taux_interet_annuel'])] : []),
        ];
    }
}
