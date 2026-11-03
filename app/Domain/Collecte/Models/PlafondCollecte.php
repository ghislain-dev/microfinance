<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Models;

use App\Support\Audit\EstAudite;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Devise $devise
 * @property Montant $plafond_retrait
 * @property Montant $plafond_especes
 */
#[Table('plafonds_collecte')]
class PlafondCollecte extends Model
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
            'devise' => Devise::class,
            'plafond_retrait' => MontantCast::class.':devise',
            'plafond_especes' => MontantCast::class.':devise',
        ];
    }
}
