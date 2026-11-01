<?php

declare(strict_types=1);

namespace App\Domain\Credit\Actions;

use App\Domain\Credit\Models\ProduitCredit;
use App\Domain\Credit\Periodicite;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\Pourcentage;
use InvalidArgumentException;

/**
 * Création et modification des produits de crédit (admin, gérant). Le code et la devise ne changent jamais ;
 * une modification ne touche pas les prêts déjà demandés (conditions figées sur le prêt).
 */
final class EnregistrerProduitCredit
{
    /**
     * @param  array<string, mixed>  $donnees  données validées
     */
    public function creer(User $acteur, array $donnees): ProduitCredit
    {
        AutoriserDansOrganisation::autoriser($acteur, 'create', ProduitCredit::class);
        $devise = Devise::from((string) $donnees['devise']);

        $produit = new ProduitCredit(['code' => (string) $donnees['code'], 'devise' => $devise]);
        $produit->fill($this->parametres($donnees, $devise));
        $produit->forceFill(['organisation_id' => $acteur->organisation_id])->save();

        return $produit->refresh();
    }

    /**
     * @param  array<string, mixed>  $donnees  données validées (code et devise ignorés)
     */
    public function modifier(User $acteur, ProduitCredit $produit, array $donnees): ProduitCredit
    {
        AutoriserDansOrganisation::autoriser($acteur, 'update', $produit);

        $produit->fill([...$this->parametres($donnees, $produit->devise), 'actif' => (bool) ($donnees['actif'] ?? $produit->actif)]);
        $produit->save();

        return $produit;
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function parametres(array $donnees, Devise $devise): array
    {
        $minimum = Montant::depuisSaisie((string) $donnees['montant_minimum'], $devise);
        $maximum = Montant::depuisSaisie((string) $donnees['montant_maximum'], $devise);
        if (! $minimum->estPositif() || $maximum->comparer($minimum) < 0) {
            throw new InvalidArgumentException('Montants : minimum positif et maximum supérieur ou égal au minimum.');
        }
        $duree = (int) $donnees['duree_max'];
        $multiple = (int) $donnees['multiple_epargne'];
        if ($duree < 1 || $multiple < 1) {
            throw new InvalidArgumentException('Durée et multiple de l’épargne : au moins 1.');
        }
        $garantie = Pourcentage::pointsDeBase((string) $donnees['garantie_epargne'], 'Garantie');
        if ($garantie > 10_000) {
            throw new InvalidArgumentException('Garantie : 100 % au plus.');
        }

        return [
            'libelle' => trim((string) $donnees['libelle']),
            'taux_mensuel_pb' => Pourcentage::pointsDeBase((string) $donnees['taux_mensuel'], "Taux d'intérêt"),
            'periodicite' => Periodicite::from((string) $donnees['periodicite']),
            'duree_max' => $duree,
            'montant_minimum' => $minimum,
            'montant_maximum' => $maximum,
            'anciennete_mois' => max(0, (int) $donnees['anciennete_mois']),
            'multiple_epargne' => $multiple,
            'garantie_epargne_pb' => $garantie,
            'penalite_jour_pb' => Pourcentage::pointsDeBase((string) $donnees['penalite_jour'], 'Pénalité'),
            'delai_grace_jours' => max(0, (int) $donnees['delai_grace_jours']),
        ];
    }
}
