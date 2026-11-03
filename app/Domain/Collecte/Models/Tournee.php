<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Models;

use App\Domain\Collecte\Policies\CollectePolicy;
use App\Domain\Membres\Models\Membre;
use App\Support\Audit\EstAudite;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Affectation d'un membre à la tournée d'un collecteur (retire_le renseigné quand le gérant la retire).
 *
 * @property Carbon|null $retire_le
 */
#[Table('tournees')]
#[UsePolicy(CollectePolicy::class)]
class Tournee extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

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
        return ['collecteur_id' => 'integer', 'membre_id' => 'integer', 'retire_le' => 'datetime'];
    }
}
