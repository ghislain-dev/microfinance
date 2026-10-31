<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Models;

use App\Support\BaseDeDonnees\EnInsertionSeule;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $type
 * @property Devise $devise
 * @property Montant $montant
 * @property Montant $frais
 */
#[Table('mouvements_epargne')]
class MouvementEpargne extends Model
{
    use AppartientAOrganisation, EnInsertionSeule;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    public function libelleType(): string
    {
        return match ($this->type) {
            'depot' => 'Dépôt',
            'retrait' => 'Retrait',
            'virement_sortant' => 'Virement émis',
            'virement_entrant' => 'Virement reçu',
            'change_sortant' => 'Change (devise cédée)',
            'change_entrant' => 'Change (devise reçue)',
            default => $this->type,
        };
    }

    /**
     * @return HasOne<Recu, $this>
     */
    public function recu(): HasOne
    {
        return $this->hasOne(Recu::class);
    }

    /**
     * @return BelongsTo<CompteEpargne, $this>
     */
    public function compteEpargne(): BelongsTo
    {
        return $this->belongsTo(CompteEpargne::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'devise' => Devise::class,
            'montant' => MontantCast::class.':devise',
            'frais' => MontantCast::class.':devise',
        ];
    }
}
