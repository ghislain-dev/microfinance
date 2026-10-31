<?php

declare(strict_types=1);

namespace App\Domain\Caisse\Models;

use App\Domain\Caisse\MomentComptage;
use App\Support\BaseDeDonnees\EnInsertionSeule;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * @property MomentComptage $moment
 * @property Devise $devise
 * @property Montant $theorique
 * @property Montant $compte
 * @property Montant $ecart
 */
#[Fillable(['moment', 'devise', 'theorique', 'compte', 'ecart'])]
#[Table('ecarts_caisse')]
class EcartCaisse extends Model
{
    use AppartientAOrganisation, EnInsertionSeule;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'moment' => MomentComptage::class,
            'devise' => Devise::class,
            'theorique' => MontantCast::class.':devise',
            'compte' => MontantCast::class.':devise',
            'ecart' => MontantCast::class.':devise',
        ];
    }
}
