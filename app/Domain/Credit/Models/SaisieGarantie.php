<?php

declare(strict_types=1);

namespace App\Domain\Credit\Models;

use App\Domain\Credit\Policies\PretPolicy;
use App\Support\Audit\EstAudite;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Devise $devise
 * @property Montant $montant
 * @property string $statut
 * @property int $demande_par_id
 * @property int $pret_id
 */
#[Table('saisies_garantie')]
#[UsePolicy(PretPolicy::class)]
class SaisieGarantie extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['devise' => Devise::class, 'montant' => MontantCast::class.':devise', 'demande_par_id' => 'integer', 'pret_id' => 'integer'];
    }
}
