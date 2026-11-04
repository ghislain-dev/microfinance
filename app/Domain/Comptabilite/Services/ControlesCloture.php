<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Services;

use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Caisse\StatutSession;
use App\Domain\Collecte\Models\VersementCollecte;
use App\Domain\Epargne\Remuneration\Models\Remuneration;
use App\Domain\Epargne\Remuneration\StatutRemuneration;
use App\Domain\Finance\Services\VerificateurSoldes;
use App\Domain\Organisation\Models\Organisation;
use App\Domain\Tontines\Models\Partage;
use Carbon\CarbonImmutable;

/**
 * Contrôles bloquants avant la clôture d'un mois (ADR 023). Chaque problème est décrit pour l'écran.
 */
final class ControlesCloture
{
    public function __construct(private readonly VerificateurSoldes $verificateur) {}

    /**
     * @return list<string> problèmes trouvés (vide : le mois peut être clôturé)
     */
    public function verifier(Organisation $organisation, CarbonImmutable $fin): array
    {
        $limite = $fin->endOfDay();
        $problemes = [];

        $sessions = SessionCaisse::query()->where('ouverte_le', '<=', $limite)->where('statut', '<>', StatutSession::Fermee->value)->count();
        if ($sessions > 0) {
            $problemes[] = "{$sessions} session(s) de caisse ouverte(s) ou en écart à justifier.";
        }
        $versements = VersementCollecte::query()->where('created_at', '<=', $limite)->where('statut', 'ecart_a_justifier')->count();
        if ($versements > 0) {
            $problemes[] = "{$versements} versement(s) de collecteur avec un écart à valider.";
        }
        // Lot rattaché à sa période (« 2026-01 » ou date de référence), quelle que soit sa date de calcul.
        $lots = Remuneration::query()->where('statut', StatutRemuneration::Brouillon->value)->pluck('periode')
            ->filter(fn ($periode) => substr((string) $periode, 0, 7) <= $fin->format('Y-m'))->count();
        if ($lots > 0) {
            $problemes[] = "{$lots} lot(s) d'intérêts ou de ristournes à valider.";
        }
        $partages = Partage::query()->where('statut', 'brouillon')->where('created_at', '<=', $limite)->count();
        if ($partages > 0) {
            $problemes[] = "{$partages} partage(s) de tontine à valider par le comité.";
        }
        $ecarts = count($this->verificateur->ecartsDeCache($organisation)) + count($this->verificateur->devisesDesequilibrees($organisation));
        if ($ecarts > 0) {
            $problemes[] = "{$ecarts} écart(s) à la vérification des soldes (finance:verifier-soldes).";
        }

        return $problemes;
    }
}
