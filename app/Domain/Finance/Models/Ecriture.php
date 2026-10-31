<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Support\BaseDeDonnees\EnInsertionSeule;
use App\Support\Montant\Devise;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'écriture (partie double). Créée uniquement par le MoteurFinancier.
 *
 * @property int $debit
 * @property int $credit
 * @property Devise $devise
 */
#[Fillable(['compte_id', 'debit', 'credit', 'devise'])]
class Ecriture extends Model
{
    use AppartientAOrganisation, EnInsertionSeule;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Operation, $this>
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    /**
     * @return BelongsTo<Compte, $this>
     */
    public function compte(): BelongsTo
    {
        return $this->belongsTo(Compte::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'debit' => 'integer',
            'credit' => 'integer',
            'devise' => Devise::class,
        ];
    }
}
