<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Models;

use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Policies\AidePolicy;
use App\Support\Audit\EstAudite;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('aides_tontine')]
#[UsePolicy(AidePolicy::class)]
class Aide extends Model
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
     * @return BelongsTo<Membre, $this>
     */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }
}
