<?php

declare(strict_types=1);

namespace App\Domain\Credit\Models;

use App\Domain\Membres\Models\Membre;
use App\Support\Audit\EstAudite;
use App\Support\BaseDeDonnees\EnInsertionSeule;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Caution d'un autre membre (engagement, sans blocage) ou bien matériel (sans écriture). Insertion seule.
 *
 * @property string $type caution | bien
 * @property int|null $membre_id
 * @property Devise $devise
 * @property Montant|null $montant
 * @property string|null $description
 */
#[Table('garanties_pret')]
class GarantiePret extends Model
{
    use AppartientAOrganisation, EnInsertionSeule, EstAudite;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return BelongsTo<Membre, $this>
     */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['devise' => Devise::class, 'montant' => MontantCast::class.':devise'];
    }
}
