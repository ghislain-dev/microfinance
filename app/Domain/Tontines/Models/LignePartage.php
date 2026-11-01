<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Models;

use App\Domain\Membres\Models\Membre;
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
 * @property Montant $part
 * @property Montant $compensation dette réglée sur la part
 * @property Montant $verse versé sur l'épargne
 */
#[Table('lignes_partage_tontine')]
class LignePartage extends Model
{
    use AppartientAOrganisation, EnInsertionSeule;

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
        return [
            'devise' => Devise::class,
            'part' => MontantCast::class.':devise',
            'compensation' => MontantCast::class.':devise',
            'verse' => MontantCast::class.':devise',
        ];
    }
}
