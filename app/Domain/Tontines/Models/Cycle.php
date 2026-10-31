<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Models;

use App\Domain\Finance\Models\Compte;
use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\ModeTours;
use App\Domain\Tontines\Periodicite;
use App\Domain\Tontines\Policies\CyclePolicy;
use App\Domain\Tontines\StatutCycle;
use App\Support\Audit\EstAudite;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Cycle d'un groupe. Tous les attributs sont posés par les actions du module (pas d'assignation de masse).
 *
 * @property Devise $devise
 * @property Periodicite $periodicite
 * @property Carbon $date_debut
 * @property Montant $montant_cotisation
 * @property Montant $cotisation_fonds_social
 * @property Montant $penalite_absence
 * @property Montant $penalite_retard
 * @property Montant $penalite_impaye
 * @property ModeTours|null $mode_tours
 * @property StatutCycle $statut
 */
#[Table('cycles_tontine')]
#[UsePolicy(CyclePolicy::class)]
class Cycle extends Model
{
    use AppartientAOrganisation, EstAudite;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return BelongsTo<Groupe, $this>
     */
    public function groupe(): BelongsTo
    {
        return $this->belongsTo(Groupe::class, 'groupe_id');
    }

    /**
     * @return BelongsToMany<Membre, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Membre::class, 'cycle_participants', 'cycle_id')->withTimestamps();
    }

    /**
     * @return BelongsTo<Compte, $this>
     */
    public function compteFonds(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'compte_fonds_id');
    }

    /**
     * @return BelongsTo<Compte, $this>
     */
    public function compteFondsSocial(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'compte_fonds_social_id');
    }

    /**
     * @return BelongsTo<Compte, $this>
     */
    public function compteCreances(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'compte_creances_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'devise' => Devise::class,
            'periodicite' => Periodicite::class,
            'date_debut' => 'date',
            'nombre_seances' => 'integer',
            'parts_min' => 'integer',
            'parts_max' => 'integer',
            'montant_cotisation' => MontantCast::class.':devise',
            'cotisation_fonds_social' => MontantCast::class.':devise',
            'penalite_absence' => MontantCast::class.':devise',
            'penalite_retard' => MontantCast::class.':devise',
            'penalite_impaye' => MontantCast::class.':devise',
            'mode_tours' => ModeTours::class,
            'statut' => StatutCycle::class,
        ];
    }
}
