<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Models;

use App\Support\BaseDeDonnees\EnInsertionSeule;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Résultat d'une opération saisie hors-ligne (cree | rejete) ; la clé du téléphone la rend unique.
 *
 * @property string $statut
 * @property string|null $raison
 * @property int|null $operation_id
 */
#[Table('operations_sync')]
class OperationSync extends Model
{
    use AppartientAOrganisation, EnInsertionSeule;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['donnees' => 'array', 'saisi_le' => 'datetime', 'operation_id' => 'integer', 'membre_id' => 'integer'];
    }
}
