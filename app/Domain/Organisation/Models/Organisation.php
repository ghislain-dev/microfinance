<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Models;

use App\Domain\Organisation\Policies\OrganisationPolicy;
use App\Models\User;
use App\Support\Audit\EstAudite;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Organisation cliente (tontine, AVEC, coopérative, microfinance) : la frontière d'isolement des données.
 *
 * @property string|null $logo_chemin
 */
#[Fillable(['nom', 'code', 'actif'])]
#[UseFactory(OrganisationFactory::class)]
#[UsePolicy(OrganisationPolicy::class)]
class Organisation extends Model
{
    use EstAudite;

    /** @use HasFactory<OrganisationFactory> */
    use HasFactory;

    /**
     * @return HasMany<Agence, $this>
     */
    public function agences(): HasMany
    {
        return $this->hasMany(Agence::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function utilisateurs(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'rappel_echeance_jours' => 'integer',
        ];
    }
}
