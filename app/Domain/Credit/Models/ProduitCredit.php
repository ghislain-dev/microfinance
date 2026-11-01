<?php

declare(strict_types=1);

namespace App\Domain\Credit\Models;

use App\Domain\Credit\Periodicite;
use App\Domain\Credit\Policies\ProduitCreditPolicy;
use App\Support\Audit\EstAudite;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Produit de crédit. Ses conditions sont copiées sur chaque prêt à la demande.
 *
 * @property Devise $devise
 * @property int $taux_mensuel_pb
 * @property Periodicite $periodicite
 * @property int $duree_max
 * @property Montant $montant_minimum
 * @property Montant $montant_maximum
 * @property int $anciennete_mois
 * @property int $multiple_epargne
 * @property int $garantie_epargne_pb
 * @property int $penalite_jour_pb
 * @property int $delai_grace_jours
 * @property bool $actif
 */
#[Fillable(['code', 'libelle', 'devise', 'taux_mensuel_pb', 'periodicite', 'duree_max', 'montant_minimum', 'montant_maximum',
    'anciennete_mois', 'multiple_epargne', 'garantie_epargne_pb', 'penalite_jour_pb', 'delai_grace_jours', 'actif'])]
#[Table('produits_credit')]
#[UsePolicy(ProduitCreditPolicy::class)]
class ProduitCredit extends Model
{
    use AppartientAOrganisation, EstAudite;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'devise' => Devise::class,
            'taux_mensuel_pb' => 'integer',
            'periodicite' => Periodicite::class,
            'duree_max' => 'integer',
            'montant_minimum' => MontantCast::class.':devise',
            'montant_maximum' => MontantCast::class.':devise',
            'anciennete_mois' => 'integer',
            'multiple_epargne' => 'integer',
            'garantie_epargne_pb' => 'integer',
            'penalite_jour_pb' => 'integer',
            'delai_grace_jours' => 'integer',
            'actif' => 'boolean',
        ];
    }
}
