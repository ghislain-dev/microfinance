<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Organisation\Models\Agence;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'telephone', 'password', 'actif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use AppartientAOrganisation;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Agences de l'utilisateur (plusieurs possibles, toutes dans son organisation).
     *
     * @return BelongsToMany<Agence, $this>
     */
    public function agences(): BelongsToMany
    {
        return $this->belongsToMany(Agence::class)->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
        ];
    }
}
