<?php

declare(strict_types=1);

namespace App\Domain\Caisse\Models;

use App\Domain\Caisse\MomentComptage;
use App\Domain\Caisse\Policies\SessionCaissePolicy;
use App\Domain\Caisse\StatutSession;
use App\Models\User;
use App\Support\Audit\EstAudite;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property StatutSession $statut
 * @property Carbon $ouverte_le
 * @property Carbon|null $fermee_le
 */
#[Fillable(['statut', 'ouverte_le'])]
#[Table('sessions_caisse')]
#[UsePolicy(SessionCaissePolicy::class)]
class SessionCaisse extends Model
{
    use AppartientAOrganisation, EstAudite;

    /**
     * @return BelongsTo<Caisse, $this>
     */
    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function caissier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caissier_id');
    }

    /**
     * @return HasMany<EcartCaisse, $this>
     */
    public function ecarts(): HasMany
    {
        return $this->hasMany(EcartCaisse::class);
    }

    /**
     * Comptage dont les écarts attendent la validation du gérant.
     */
    public function momentEnAttente(): ?MomentComptage
    {
        return match ($this->statut) {
            StatutSession::OuvertureAValider => MomentComptage::Ouverture,
            StatutSession::EcartAJustifier => MomentComptage::Cloture,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'statut' => StatutSession::class,
            'ouverte_le' => 'datetime',
            'fermee_le' => 'datetime',
        ];
    }
}
