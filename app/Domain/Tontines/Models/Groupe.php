<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Models;

use App\Domain\Membres\Models\Membre;
use App\Domain\Organisation\Models\Agence;
use App\Domain\Tontines\Policies\GroupePolicy;
use App\Domain\Tontines\TypeGroupe;
use App\Support\Audit\EstAudite;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property TypeGroupe $type
 */
#[Fillable(['code', 'nom', 'type', 'actif'])]
#[Table('groupes_tontine')]
#[UsePolicy(GroupePolicy::class)]
class Groupe extends Model
{
    use AppartientAOrganisation, EstAudite;

    /**
     * @return BelongsTo<Agence, $this>
     */
    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    /**
     * @return BelongsToMany<Membre, $this>
     */
    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(Membre::class, 'groupe_membres', 'groupe_id')->withTimestamps();
    }

    /**
     * @return HasMany<Cycle, $this>
     */
    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class, 'groupe_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => TypeGroupe::class, 'actif' => 'boolean'];
    }
}
