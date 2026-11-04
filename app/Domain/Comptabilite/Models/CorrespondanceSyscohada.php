<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Models;

use App\Domain\Comptabilite\Policies\ComptabilitePolicy;
use App\Support\Audit\EstAudite;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

#[Table('correspondances_syscohada')]
#[UsePolicy(ComptabilitePolicy::class)]
class CorrespondanceSyscohada extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];
}
