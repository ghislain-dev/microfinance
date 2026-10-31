<?php

declare(strict_types=1);

namespace App\Domain\Change\Models;

use App\Support\BaseDeDonnees\EnInsertionSeule;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Montant $montant_source
 * @property Montant $montant_destination
 * @property Montant $marge
 */
#[Table('conversions')]
class Conversion extends Model
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
        return [
            'devise_source' => Devise::class,
            'devise_destination' => Devise::class,
            'montant_source' => MontantCast::class.':devise_source',
            'montant_destination' => MontantCast::class.':devise_destination',
            'marge' => MontantCast::class.':devise_destination',
        ];
    }
}
