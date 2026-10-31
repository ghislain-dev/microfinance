<?php

declare(strict_types=1);

namespace App\Domain\Caisse\Models;

use App\Domain\Caisse\Policies\CaissePolicy;
use App\Domain\Finance\Models\Compte;
use App\Domain\Organisation\Models\Agence;
use App\Support\Audit\EstAudite;
use App\Support\Montant\Devise;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'libelle', 'actif'])]
#[UsePolicy(CaissePolicy::class)]
class Caisse extends Model
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
     * @return BelongsToMany<Compte, $this>
     */
    public function comptes(): BelongsToMany
    {
        return $this->belongsToMany(Compte::class, 'caisse_comptes')->withPivot('devise');
    }

    /**
     * @return HasMany<SessionCaisse, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(SessionCaisse::class);
    }

    public function compte(Devise $devise): Compte
    {
        return $this->comptes()->wherePivot('devise', $devise->value)->firstOrFail();
    }

    public function sessionActive(): ?SessionCaisse
    {
        return $this->sessions()->whereNotNull('active_flag')->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
