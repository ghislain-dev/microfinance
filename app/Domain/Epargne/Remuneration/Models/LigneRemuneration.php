<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Remuneration\Models;

use App\Domain\Epargne\Models\CompteEpargne;
use App\Support\BaseDeDonnees\EnInsertionSeule;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Devise $devise
 * @property Montant $base
 * @property Montant $montant
 */
#[Table('remuneration_lignes')]
class LigneRemuneration extends Model
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
        return ['devise' => Devise::class, 'base' => MontantCast::class.':devise', 'montant' => MontantCast::class.':devise'];
    }
}
