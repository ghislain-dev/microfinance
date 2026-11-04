<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Membres\Models\Membre;
use App\Domain\Organisation\Models\Agence;
use App\Domain\Organisation\Policies\UserPolicy;
use App\Support\Audit\EstAudite;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'telephone', 'password', 'actif'])]
#[Hidden(['password', 'remember_token'])]
#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable
{
    use AppartientAOrganisation, EstAudite;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

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
     * Membre lié à un compte d'accès mobile (ADR 022) ; null pour le personnel.
     *
     * @return BelongsTo<Membre, $this>
     */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /**
     * Identifiant du membre pour un accès mobile, null pour le personnel (lu même si la colonne n'est pas chargée).
     */
    public function membreId(): ?int
    {
        $id = $this->getAttributes()['membre_id'] ?? null;

        return $id === null ? null : (int) $id;
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
            'membre_id' => 'integer',
            'mot_de_passe_provisoire' => 'boolean',
        ];
    }
}
