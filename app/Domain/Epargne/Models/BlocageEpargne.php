<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Models;

use App\Support\Audit\EstAudite;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Montant non retirable d'un compte d'épargne (garantie d'un prêt), posé et levé par le service Blocages.
 *
 * @property Devise $devise
 * @property Montant $montant
 * @property Carbon|null $leve_le
 */
#[Table('blocages_epargne')]
class BlocageEpargne extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['devise' => Devise::class, 'montant' => MontantCast::class.':devise', 'leve_le' => 'datetime'];
    }
}
