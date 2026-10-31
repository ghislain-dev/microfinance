<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Models;

use App\Domain\Epargne\Policies\TypeCompteEpargnePolicy;
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
 * Produit d'épargne. Les paramètres s'appliquent au moment de chaque opération.
 *
 * @property Devise $devise
 * @property Montant $solde_minimum
 * @property Montant $depot_minimum
 * @property Montant $frais_retrait
 * @property int|null $duree_blocage_mois
 */
#[Fillable(['code', 'libelle', 'devise', 'solde_minimum', 'depot_minimum', 'frais_retrait', 'duree_blocage_mois', 'actif'])]
#[Table('types_comptes_epargne')]
#[UsePolicy(TypeCompteEpargnePolicy::class)]
class TypeCompteEpargne extends Model
{
    use AppartientAOrganisation, EstAudite;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'devise' => Devise::class,
            'solde_minimum' => MontantCast::class.':devise',
            'depot_minimum' => MontantCast::class.':devise',
            'frais_retrait' => MontantCast::class.':devise',
            'duree_blocage_mois' => 'integer',
            'actif' => 'boolean',
        ];
    }
}
