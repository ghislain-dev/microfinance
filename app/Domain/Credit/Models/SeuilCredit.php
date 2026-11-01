<?php

declare(strict_types=1);

namespace App\Domain\Credit\Models;

use App\Support\Audit\EstAudite;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Au-delà de ce montant, le gérant approuve le prêt après le comité.
 *
 * @property Devise $devise
 * @property Montant $montant
 */
#[Fillable(['devise', 'montant'])]
#[Table('seuils_credit')]
class SeuilCredit extends Model
{
    use AppartientAOrganisation, EstAudite;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['devise' => Devise::class, 'montant' => MontantCast::class.':devise'];
    }
}
