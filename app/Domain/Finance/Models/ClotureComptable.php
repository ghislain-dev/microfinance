<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Support\BaseDeDonnees\EnInsertionSeule;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date_fin
 */
#[Fillable(['date_fin', 'par'])]
#[Table('clotures_comptables')]
class ClotureComptable extends Model
{
    use AppartientAOrganisation, EnInsertionSeule;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date_fin' => 'date'];
    }
}
