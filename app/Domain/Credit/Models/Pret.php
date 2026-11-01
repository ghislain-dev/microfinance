<?php

declare(strict_types=1);

namespace App\Domain\Credit\Models;

use App\Domain\Credit\MethodeCalcul;
use App\Domain\Credit\Periodicite;
use App\Domain\Credit\Policies\PretPolicy;
use App\Domain\Credit\StatutPret;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Finance\Models\Compte;
use App\Domain\Membres\Models\Membre;
use App\Support\Audit\EstAudite;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Prêt d'un membre, de la demande au solde. Tous les attributs sont posés par les actions du module.
 *
 * @property string $numero
 * @property Devise $devise
 * @property Montant $montant
 * @property int $taux_mensuel_pb
 * @property Periodicite $periodicite
 * @property int $nombre_echeances
 * @property MethodeCalcul $methode
 * @property int $garantie_epargne_pb
 * @property int $penalite_jour_pb
 * @property int $delai_grace_jours
 * @property string $objet
 * @property string|null $analyse
 * @property StatutPret $statut
 * @property int $saisi_par_id
 * @property int|null $comite_par_id
 * @property int|null $gerant_par_id
 * @property int|null $decaisse_par_id
 * @property Carbon|null $date_decaissement
 * @property Montant|null $garantie_epargne
 */
#[Table('prets')]
#[UsePolicy(PretPolicy::class)]
class Pret extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return BelongsTo<Membre, $this>
     */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /**
     * @return BelongsTo<ProduitCredit, $this>
     */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(ProduitCredit::class, 'produit_credit_id');
    }

    /**
     * @return BelongsTo<CompteEpargne, $this>
     */
    public function compteEpargne(): BelongsTo
    {
        return $this->belongsTo(CompteEpargne::class);
    }

    /**
     * @return BelongsTo<Compte, $this>
     */
    public function comptePret(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'compte_pret_id');
    }

    /**
     * @return BelongsTo<Compte, $this>
     */
    public function comptePenalites(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'compte_penalites_id');
    }

    /**
     * @return HasMany<EcheancePret, $this>
     */
    public function echeances(): HasMany
    {
        return $this->hasMany(EcheancePret::class)->orderBy('rang');
    }

    /**
     * @return HasMany<GarantiePret, $this>
     */
    public function garanties(): HasMany
    {
        return $this->hasMany(GarantiePret::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'devise' => Devise::class,
            'montant' => MontantCast::class.':devise',
            'taux_mensuel_pb' => 'integer',
            'periodicite' => Periodicite::class,
            'nombre_echeances' => 'integer',
            'methode' => MethodeCalcul::class,
            'garantie_epargne_pb' => 'integer',
            'penalite_jour_pb' => 'integer',
            'delai_grace_jours' => 'integer',
            'statut' => StatutPret::class,
            'saisi_par_id' => 'integer',
            'comite_par_id' => 'integer',
            'gerant_par_id' => 'integer',
            'decaisse_par_id' => 'integer',
            'comite_le' => 'datetime',
            'gerant_le' => 'datetime',
            'date_decaissement' => 'date',
            'garantie_epargne' => MontantCast::class.':devise',
            'solde_le' => 'datetime',
        ];
    }
}
