<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Models;

use App\Domain\Tontines\Policies\PartagePolicy;
use App\Support\Audit\EstAudite;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Devise $devise
 * @property Montant $montant_total
 */
#[Table('partages_tontine')]
#[UsePolicy(PartagePolicy::class)]
class Partage extends Model
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
     * @return HasMany<LignePartage, $this>
     */
    public function lignes(): HasMany
    {
        return $this->hasMany(LignePartage::class, 'partage_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['devise' => Devise::class, 'montant_total' => MontantCast::class.':devise', 'decide_le' => 'datetime'];
    }
}
