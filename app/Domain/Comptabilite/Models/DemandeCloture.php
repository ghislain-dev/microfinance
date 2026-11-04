<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Models;

use App\Domain\Comptabilite\Policies\ComptabilitePolicy;
use App\Support\Audit\EstAudite;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date_fin
 * @property string $statut
 * @property int $demande_par_id
 */
#[Table('demandes_cloture')]
#[UsePolicy(ComptabilitePolicy::class)]
class DemandeCloture extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date_fin' => 'date', 'demande_par_id' => 'integer', 'decide_le' => 'datetime'];
    }
}
