<?php

declare(strict_types=1);

namespace App\Support\Temps;

use App\Domain\Organisation\Models\Organisation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

/**
 * Fuseau horaire de chaque organisation (ADR 024). La base garde les horodatages en UTC ; le « jour » d'une
 * opération (date de valeur, taux du jour, échéances, clôture) est celui de l'organisation.
 */
final class Horloge
{
    /** @var array<int, array{0: string, 1: int}> organisation => [fuseau, lu à (timestamp)] */
    private static array $fuseaux = [];

    /**
     * Sans organisation donnée : celle de l'utilisateur connecté ; sans utilisateur : fuseau de l'application.
     */
    public static function fuseau(?int $organisationId = null): string
    {
        $organisationId ??= Auth::hasUser() ? (int) Auth::user()?->getAttribute('organisation_id') : null;
        if ($organisationId === null || $organisationId === 0) {
            return (string) config('app.timezone');
        }

        // Relu au plus toutes les minutes : un worker de file d'attente vit longtemps.
        [$fuseau, $lu] = self::$fuseaux[$organisationId] ?? [null, 0];
        if ($fuseau === null || time() - $lu > 60) {
            $fuseau = (string) (Organisation::withoutGlobalScopes()->whereKey($organisationId)->value('fuseau') ?? config('app.timezone'));
            self::$fuseaux[$organisationId] = [$fuseau, time()];
        }

        return $fuseau;
    }

    /**
     * Date du jour dans l'organisation, à minuit dans le fuseau de l'application (comparable aux dates lues en base).
     */
    public static function aujourdhui(?int $organisationId = null): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now(self::fuseau($organisationId))->toDateString());
    }

    /**
     * Instant (UTC en base) exprimé dans le fuseau de l'organisation, pour l'affichage.
     */
    public static function local(CarbonInterface $instant, ?int $organisationId = null): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)->setTimezone(self::fuseau($organisationId));
    }

    /**
     * Horodatage formaté dans le fuseau de l'organisation (vide si absent), pour les vues.
     */
    public static function affiche(?CarbonInterface $instant, string $format = 'd/m/Y H:i', ?int $organisationId = null): string
    {
        return $instant === null ? '' : self::local($instant, $organisationId)->format($format);
    }

    /**
     * Début du jour local, en UTC : borne des requêtes « du jour » sur les horodatages.
     */
    public static function debutDuJour(?int $organisationId = null): CarbonImmutable
    {
        return CarbonImmutable::now(self::fuseau($organisationId))->startOfDay()->utc();
    }

    public static function oublier(): void
    {
        self::$fuseaux = [];
    }
}
