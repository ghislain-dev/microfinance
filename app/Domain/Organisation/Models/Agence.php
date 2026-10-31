<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Models;

use App\Domain\Organisation\Policies\AgencePolicy;
use App\Models\User;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Database\Factories\AgenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['nom', 'code', 'actif'])]
#[UseFactory(AgenceFactory::class)]
#[UsePolicy(AgencePolicy::class)]
class Agence extends Model
{
    use AppartientAOrganisation;

    /** @use HasFactory<AgenceFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<User, $this>
     */
    public function utilisateurs(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }
}
