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
