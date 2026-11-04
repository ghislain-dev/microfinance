<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Models;

use App\Domain\Epargne\Policies\CompteEpargnePolicy;
use App\Domain\Finance\Models\Compte;
use App\Domain\Membres\Models\Membre;
use App\Support\Audit\EstAudite;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use App\Support\Temps\Horloge;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Compte d'épargne d'un membre ; son solde est celui du compte du grand livre associé.
 * Tous ses attributs sont fixés à l'ouverture (aucun n'est assignable en masse).
 *
 * @property Carbon $date_ouverture
 * @property Carbon|null $bloque_jusqu_au
 */
#[Table('comptes_epargne')]
#[UsePolicy(CompteEpargnePolicy::class)]
class CompteEpargne extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return BelongsTo<Compte, $this>
     */
    public function compte(): BelongsTo
    {
        return $this->belongsTo(Compte::class);
    }

    /**
     * @return BelongsTo<Membre, $this>
     */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /**
     * @return BelongsTo<TypeCompteEpargne, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeCompteEpargne::class, 'type_compte_epargne_id');
    }

    public function estBloque(): bool
    {
        return $this->bloque_jusqu_au !== null && Horloge::aujourdhui($this->organisation_id)->lt($this->bloque_jusqu_au);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_ouverture' => 'date',
            'bloque_jusqu_au' => 'date',
        ];
    }
}
