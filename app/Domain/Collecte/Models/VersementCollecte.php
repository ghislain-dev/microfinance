<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Models;

use App\Domain\Collecte\Policies\VersementPolicy;
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

/**
 * @property string $statut valide | ecart_a_justifier
 * @property Devise $devise
 * @property Montant $theorique
 * @property Montant $compte
 * @property Montant $ecart
 */
#[Table('versements_collecte')]
#[UsePolicy(VersementPolicy::class)]
class VersementCollecte extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function collecteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collecteur_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'devise' => Devise::class,
            'theorique' => MontantCast::class.':devise',
            'compte' => MontantCast::class.':devise',
            'ecart' => MontantCast::class.':devise',
            'billetage' => 'array',
            'collecteur_id' => 'integer',
            'caissier_id' => 'integer',
        ];
    }
}
