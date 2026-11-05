<?php

declare(strict_types=1);

namespace App\Domain\Credit\Models;

use App\Domain\Credit\ModeRemboursement;
use App\Domain\Epargne\Models\Recu;
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
 * Remboursement d'un prêt et sa répartition (pénalités, intérêts, capital), lié à l'opération du moteur.
 * Insertion seule.
 *
 * @property ModeRemboursement $mode
 * @property Devise $devise
 * @property Montant $montant
 * @property Montant $penalites
 * @property Montant $interets
 * @property Montant $capital
 */
#[Table('remboursements_pret')]
class Remboursement extends Model
{
    use AppartientAOrganisation, EnInsertionSeule;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return BelongsTo<Pret, $this>
     */
    public function pret(): BelongsTo
    {
        return $this->belongsTo(Pret::class);
    }

    /**
     * @return HasOne<Recu, $this>
     */
    public function recu(): HasOne
    {
        return $this->hasOne(Recu::class, 'remboursement_pret_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => ModeRemboursement::class,
            'devise' => Devise::class,
            'montant' => MontantCast::class.':devise',
            'penalites' => MontantCast::class.':devise',
            'interets' => MontantCast::class.':devise',
            'capital' => MontantCast::class.':devise',
        ];
    }
}
