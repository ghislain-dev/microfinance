<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Policies\OperationPolicy;
use App\Models\User;
use App\Support\BaseDeDonnees\EnInsertionSeule;
use App\Support\Montant\Devise;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * En-tête d'une opération financière. Créée uniquement par le MoteurFinancier.
 *
 * @property Devise $devise
 * @property Carbon $date_valeur
 */
#[UsePolicy(OperationPolicy::class)]
#[Fillable(['idempotency_key', 'type', 'devise', 'auteur_id', 'valide_par_id', 'extourne_de_id', 'date_valeur', 'motif'])]
class Operation extends Model
{
    use AppartientAOrganisation, EnInsertionSeule;

    public const UPDATED_AT = null;

    /**
     * @return HasMany<Ecriture, $this>
     */
    public function ecritures(): HasMany
    {
        return $this->hasMany(Ecriture::class);
    }

    /**
     * @return HasOne<Operation, $this>
     */
    public function extourne(): HasOne
    {
        return $this->hasOne(self::class, 'extourne_de_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'devise' => Devise::class,
            'date_valeur' => 'date',
        ];
    }
}
