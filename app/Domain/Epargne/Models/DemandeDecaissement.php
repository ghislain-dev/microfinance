<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Models;

use App\Domain\Epargne\Policies\DemandeDecaissementPolicy;
use App\Domain\Epargne\StatutDemande;
use App\Models\User;
use App\Support\Audit\EstAudite;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Retrait ou virement au-delà du seuil, en attente de la décision d'une seconde personne.
 * Tous les attributs sont posés par les actions du module (aucune assignation de masse).
 *
 * @property StatutDemande $statut
 * @property Devise $devise
 * @property Montant $montant
 */
#[Table('demandes_decaissement')]
#[UsePolicy(DemandeDecaissementPolicy::class)]
class DemandeDecaissement extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return BelongsTo<CompteEpargne, $this>
     */
    public function compteEpargne(): BelongsTo
    {
        return $this->belongsTo(CompteEpargne::class);
    }

    /**
     * @return BelongsTo<CompteEpargne, $this>
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(CompteEpargne::class, 'destination_compte_epargne_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decide_par_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'statut' => StatutDemande::class,
            'devise' => Devise::class,
            'montant' => MontantCast::class.':devise',
            'decide_le' => 'datetime',
        ];
    }
}
