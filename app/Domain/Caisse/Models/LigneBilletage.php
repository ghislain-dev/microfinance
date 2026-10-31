<?php

declare(strict_types=1);

namespace App\Domain\Caisse\Models;

use App\Support\BaseDeDonnees\EnInsertionSeule;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['moment', 'devise', 'coupure', 'nombre'])]
#[Table('billetages')]
class LigneBilletage extends Model
{
    use AppartientAOrganisation, EnInsertionSeule;

    public const UPDATED_AT = null;
}
