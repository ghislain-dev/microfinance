<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Journalise création, modification et suppression du modèle (« {table}.creation »…).
 *
 * @mixin Model
 */
trait EstAudite
{
    /** Changements sans intérêt pour l'audit (horodatages, rotation du jeton « se souvenir de moi »). */
    private const IGNORES_AUDIT = ['created_at', 'updated_at', 'remember_token'];

    protected static function bootEstAudite(): void
    {
        static::created(function (Model $modele) {
            app(JournalAudit::class)->enregistrer(
                $modele->getTable().'.creation', $modele, apres: self::attributsAudites($modele->getAttributes()),
            );
        });

        static::updated(function (Model $modele) {
            $apres = self::attributsAudites($modele->getChanges());
            if ($apres === []) {
                return;
            }

            app(JournalAudit::class)->enregistrer(
                $modele->getTable().'.modification', $modele,
                avant: array_intersect_key($modele->getRawOriginal(), $apres),
                apres: $apres,
            );
        });

        static::deleted(function (Model $modele) {
            app(JournalAudit::class)->enregistrer(
                $modele->getTable().'.suppression', $modele, avant: self::attributsAudites($modele->getAttributes()),
            );
        });
    }

    /**
     * @param  array<string, mixed>  $attributs
     * @return array<string, mixed>
     */
    private static function attributsAudites(array $attributs): array
    {
        return array_diff_key($attributs, array_flip(self::IGNORES_AUDIT));
    }
}
