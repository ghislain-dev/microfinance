<?php

declare(strict_types=1);

namespace App\Support\MultiOrganisation;

use App\Domain\Organisation\Models\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Isole les données par organisation (CLAUDE.md §4.8 et §5).
 *
 * - Lecture : filtre global sur l'organisation de l'utilisateur connecté.
 *   Sans utilisateur (console, commandes planifiées), pas de filtre : boucler par organisation.
 * - Écriture : organisation_id vient de l'utilisateur connecté, jamais de la requête
 *   (il ne doit pas figurer dans $fillable), et ne change jamais après création.
 *
 * @mixin Model
 */
trait AppartientAOrganisation
{
    protected static function bootAppartientAOrganisation(): void
    {
        static::addGlobalScope('organisation', function (Builder $requete) {
            // hasUser() et non user() : ne pas relancer le chargement de l'utilisateur
            // pendant qu'il est lui-même en cours de chargement (modèle User).
            if (Auth::hasUser()) {
                $requete->where(
                    $requete->getModel()->qualifyColumn('organisation_id'),
                    self::organisationConnectee(),
                );
            }
        });

        static::creating(function (Model $modele) {
            $organisationId = self::organisationConnectee();
            if ($organisationId === null) {
                return;
            }

            if ($modele->getAttribute('organisation_id') === null) {
                $modele->setAttribute('organisation_id', $organisationId);
            } elseif ((int) $modele->getAttribute('organisation_id') !== $organisationId) {
                throw new OrganisationIncoherente('Création refusée : autre organisation que celle de l’utilisateur.');
            }
        });

        static::updating(function (Model $modele) {
            if ($modele->isDirty('organisation_id')) {
                throw new OrganisationIncoherente('Une ligne ne change jamais d’organisation.');
            }
        });
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    private static function organisationConnectee(): ?int
    {
        $id = Auth::hasUser() ? Auth::user()?->getAttribute('organisation_id') : null;

        return $id === null ? null : (int) $id;
    }
}
