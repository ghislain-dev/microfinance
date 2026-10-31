<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Remuneration;

use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Remuneration\Models\LigneRemuneration;
use App\Domain\Epargne\Remuneration\Models\Remuneration;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\StatutMembre;
use App\Domain\Organisation\Models\Organisation;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Création des lots (brouillon) et sélection des comptes éligibles : membres actifs seulement
 * (décision du 2026-10-31), solde positif à la date de référence.
 */
final class Lots
{
    public function __construct(private readonly MoteurFinancier $moteur) {}

    /**
     * @param  callable(CompteEpargne): bool|null  $filtre
     * @return array<int, array{compte: CompteEpargne, base: Montant}>
     */
    public function eligibles(Organisation $organisation, Devise $devise, CarbonInterface $date, ?callable $filtre = null): array
    {
        /** @var Collection<int, CompteEpargne> $comptes */
        $comptes = CompteEpargne::query()->withoutGlobalScopes()->with(['type', 'compte', 'membre'])
            ->where('organisation_id', $organisation->id)
            ->whereHas('type', fn ($q) => $q->withoutGlobalScopes()->where('devise', $devise->value))
            ->whereHas('membre', fn ($q) => $q->withoutGlobalScopes()->where('statut', StatutMembre::Actif->value))
            ->orderBy('id')->get();
        if ($filtre !== null) {
            $comptes = $comptes->filter($filtre);
        }

        $soldes = $this->moteur->soldesAu($comptes->map(fn (CompteEpargne $c) => $c->compte), $date);

        return $comptes->map(fn (CompteEpargne $c) => ['compte' => $c, 'base' => $soldes[$c->compte_id]])
            ->filter(fn (array $l) => $l['base']->estPositif())->values()->all();
    }

    /**
     * @param  list<array{compte: CompteEpargne, base: Montant, montant: Montant}>  $lignes
     */
    public function creer(Organisation $organisation, string $type, Devise $devise, string $periode, CarbonInterface $date, array $lignes): Remuneration
    {
        $lignes = array_values(array_filter($lignes, fn (array $l) => $l['montant']->estPositif()));
        if ($lignes === []) {
            throw new RemunerationRefusee("Aucun montant à verser ({$type} {$devise->value} {$periode}) : aucun lot créé.");
        }
        $total = array_reduce($lignes, fn (Montant $t, array $l) => $t->plus($l['montant']), Montant::zero($devise));

        try {
            return DB::transaction(function () use ($organisation, $type, $devise, $periode, $date, $lignes, $total) {
                $lot = new Remuneration;
                $lot->forceFill([
                    'organisation_id' => $organisation->id, 'type' => $type, 'devise' => $devise, 'periode' => $periode,
                    'date_reference' => $date->toDateString(), 'montant_total' => $total,
                    'statut' => StatutRemuneration::Brouillon, 'cree_par_id' => Auth::hasUser() ? Auth::id() : null,
                ])->save();

                foreach ($lignes as $l) {
                    (new LigneRemuneration)->forceFill([
                        'organisation_id' => $organisation->id, 'remuneration_id' => $lot->id,
                        'compte_epargne_id' => $l['compte']->id, 'devise' => $devise,
                        'base' => $l['base'], 'montant' => $l['montant'],
                    ])->save();
                }

                return $lot;
            });
        } catch (UniqueConstraintViolationException) {
            throw new RemunerationRefusee("Un lot {$type} {$devise->value} existe déjà pour {$periode} (rejetez-le pour recalculer).");
        }
    }
}
