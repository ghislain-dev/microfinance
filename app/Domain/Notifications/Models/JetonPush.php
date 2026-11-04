<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('jetons_push')]
class JetonPush extends Model
{
    use AppartientAOrganisation;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['user_id' => 'integer'];
    }
}
