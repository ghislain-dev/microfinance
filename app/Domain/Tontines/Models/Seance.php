<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Models;

use App\Domain\Tontines\Policies\SeancePolicy;
use App\Domain\Tontines\StatutSeance;
use App\Support\Audit\EstAudite;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property StatutSeance $statut
 * @property Carbon $date_prevue
 */
#[Table('seances_tontine')]
#[UsePolicy(SeancePolicy::class)]
class Seance extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return BelongsTo<Cycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class, 'cycle_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['statut' => StatutSeance::class, 'date_prevue' => 'date', 'tenue_le' => 'datetime'];
    }
}
