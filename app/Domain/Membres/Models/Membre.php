<?php

declare(strict_types=1);

namespace App\Domain\Membres\Models;

use App\Domain\Membres\Policies\MembrePolicy;
use App\Domain\Membres\Sexe;
use App\Domain\Membres\StatutMembre;
use App\Domain\Organisation\Models\Agence;
use App\Support\Audit\EstAudite;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Database\Factories\MembreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Membre d'une organisation. Jamais supprimé (historique financier) : on change son statut.
 *
 * @property Sexe $sexe
 * @property StatutMembre $statut
 * @property Carbon $date_naissance
 */
#[Fillable(['nom', 'postnom', 'prenom', 'sexe', 'date_naissance', 'telephone', 'piece_type', 'piece_numero', 'adresse'])]
#[Hidden(['piece_numero', 'piece_empreinte'])]
#[UseFactory(MembreFactory::class)]
#[UsePolicy(MembrePolicy::class)]
class Membre extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @use HasFactory<MembreFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Agence, $this>
     */
    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    public function nomComplet(): string
    {
        return trim(implode(' ', array_filter([$this->nom, $this->postnom, $this->prenom])));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sexe' => Sexe::class,
            'statut' => StatutMembre::class,
            'date_naissance' => 'date',
            'piece_numero' => 'encrypted',
        ];
    }
}
