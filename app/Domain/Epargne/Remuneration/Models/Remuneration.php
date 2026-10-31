<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Remuneration\Models;

use App\Domain\Epargne\Remuneration\Policies\RemunerationPolicy;
use App\Domain\Epargne\Remuneration\StatutRemuneration;
use App\Models\User;
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
use Illuminate\Support\Carbon;

/**
 * @property string $type
 * @property Devise $devise
 * @property Montant $montant_total
 * @property StatutRemuneration $statut
 * @property Carbon $date_reference
 */
#[Table('remunerations')]
#[UsePolicy(RemunerationPolicy::class)]
class Remuneration extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return HasMany<LigneRemuneration, $this>
     */
    public function lignes(): HasMany
    {
        return $this->hasMany(LigneRemuneration::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decide_par_id');
    }

    public function libelleType(): string
    {
        return $this->type === 'interets' ? 'Intérêts' : 'Ristourne';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'devise' => Devise::class,
            'montant_total' => MontantCast::class.':devise',
            'statut' => StatutRemuneration::class,
            'date_reference' => 'date',
            'decide_le' => 'datetime',
        ];
    }
}
