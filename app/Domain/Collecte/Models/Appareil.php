<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Models;

use App\Domain\Collecte\Policies\AppareilPolicy;
use App\Models\User;
use App\Support\Audit\EstAudite;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Téléphone d'un collecteur : en_attente → autorise (par le gérant) → revoque.
 *
 * @property string $statut
 * @property string $modele
 * @property Carbon|null $derniere_sync_le
 */
#[Table('appareils')]
#[UsePolicy(AppareilPolicy::class)]
class Appareil extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function collecteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function libelleStatut(): string
    {
        return ['en_attente' => 'En attente', 'autorise' => 'Autorisé', 'revoque' => 'Révoqué'][$this->statut] ?? $this->statut;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'autorise_le' => 'datetime', 'revoque_le' => 'datetime', 'derniere_sync_le' => 'datetime'];
    }
}
