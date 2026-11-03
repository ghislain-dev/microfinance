<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Models;

use App\Support\Audit\EstAudite;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $delai_max_heures
 */
#[Table('parametres_collecte')]
class ParametresCollecte extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['delai_max_heures' => 'integer'];
    }
}
