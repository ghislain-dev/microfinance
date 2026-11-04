<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Models;

use App\Domain\Comptabilite\Policies\ComptabilitePolicy;
use App\Support\Audit\EstAudite;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $annee
 * @property Devise $devise
 * @property Montant $resultat
 * @property Montant $montant
 * @property string $statut
 * @property int $propose_par_id
 */
#[Table('affectations_resultat')]
#[UsePolicy(ComptabilitePolicy::class)]
class AffectationResultat extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'annee' => 'integer',
            'devise' => Devise::class,
            'resultat' => MontantCast::class.':devise',
            'montant' => MontantCast::class.':devise',
            'propose_par_id' => 'integer',
        ];
    }
}
