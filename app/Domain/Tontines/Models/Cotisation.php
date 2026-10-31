<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Models;

use App\Support\BaseDeDonnees\EnInsertionSeule;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('cotisations_tontine')]
class Cotisation extends Model
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
        return ['montant' => 'integer', 'parts' => 'integer'];
    }
}
