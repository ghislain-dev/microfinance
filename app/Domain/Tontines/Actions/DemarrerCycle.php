<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Actions;

use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\StatutMembre;
use App\Domain\Tontines\Events\CycleDemarre;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Groupe;
use App\Domain\Tontines\Models\Seance;
use App\Domain\Tontines\Models\Tour;
use App\Domain\Tontines\ModeTours;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\StatutSeance;
use App\Domain\Tontines\TontineRefusee;
use App\Domain\Tontines\TypeGroupe;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Audit\JournalAudit;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Démarre un cycle : participants figés (membres actifs du groupe), comptes du cycle ouverts
 * (fonds, fonds social, créances de pénalités), séances planifiées. Rotative : une séance par participant ;
 * ordre des tours tiré au sort une fois (random_int, enregistré et audité) ou fixé par le gérant ;
 * enchère : tour attribué à chaque séance.
 */
final class DemarrerCycle
{
    public function __construct(
        private readonly OuvrirCompte $ouvrirCompte,
        private readonly JournalAudit $journal,
        private readonly MoteurFinancier $moteur,
    ) {}

    /**
     * @param  list<int>|null  $ordre  identifiants des membres dans l'ordre de passage (mode « ordre fixé »)
     */
    public function executer(User $acteur, Cycle $cycle, ?array $ordre = null): Cycle
    {
        AutoriserDansOrganisation::autoriser($acteur, 'update', $cycle);

        try {
            return DB::transaction(function () use ($cycle, $ordre) {
                $groupe = Groupe::query()->with('agence')->whereKey($cycle->groupe_id)->lockForUpdate()->firstOrFail();
                $cycle = Cycle::query()->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
                if ($cycle->statut !== StatutCycle::EnPreparation) {
                    throw new TontineRefusee('Ce cycle a déjà démarré.');
                }
                if ($groupe->cycles()->where('statut', StatutCycle::EnCours->value)->exists()) {
                    throw new TontineRefusee("Le groupe {$groupe->code} a déjà un cycle en cours.");
                }
                /** @var list<int> $participants */
                $participants = $groupe->membres()->where('statut', StatutMembre::Actif->value)
                    ->orderBy('membres.id')->pluck('membres.id')->map(fn ($id) => (int) $id)->values()->all();
                if (count($participants) < 2) {
                    throw new TontineRefusee('Il faut au moins deux membres actifs pour démarrer un cycle.');
                }

                $prefixe = "TONT-{$groupe->code}-C{$cycle->numero}";
                $ouvrir = fn (string $suffixe, string $libelle, NatureCompte $nature) => $this->ouvrirCompte->executer(
                    "{$prefixe}-{$suffixe}", "{$libelle} — {$groupe->nom} cycle {$cycle->numero}", $nature, $cycle->devise, $groupe->agence);

                $cycle->forceFill([
                    'statut' => StatutCycle::EnCours,
                    'nombre_seances' => $groupe->type === TypeGroupe::Rotative ? count($participants) : $cycle->nombre_seances,
                    'compte_fonds_id' => $ouvrir('FONDS', 'Fonds du cycle', NatureCompte::CycleTontine)->id,
                    'compte_fonds_social_id' => $ouvrir('FS', 'Fonds social', NatureCompte::FondsSocial)->id,
                    'compte_creances_id' => $ouvrir('CREANCES', 'Dettes des membres', NatureCompte::CreanceMembre)->id,
                ])->save();
                $cycle->participants()->attach(collect($participants)->mapWithKeys(fn (int $id) => [$id => ['organisation_id' => $cycle->organisation_id]])->all());

                for ($rang = 1; $rang <= (int) $cycle->nombre_seances; $rang++) {
                    (new Seance)->forceFill([
                        'organisation_id' => $cycle->organisation_id, 'cycle_id' => $cycle->id, 'rang' => $rang,
                        'date_prevue' => $cycle->periodicite->dateSeance(CarbonImmutable::parse($cycle->date_debut), $rang)->toDateString(),
                        'statut' => StatutSeance::Prevue,
                    ])->save();
                }

                $this->fixerTours($cycle, $participants, $ordre);
                $this->reporterFondsSocial($groupe, $cycle);
                CycleDemarre::dispatch($cycle);

                return $cycle;
            });
        } catch (UniqueConstraintViolationException) {
            throw new TontineRefusee('Le groupe a déjà un cycle en cours.');
        }
    }

    /**
     * Le solde du fonds social du cycle clôturé précédent passe au nouveau cycle (décision du 2026-10-31).
     */
    private function reporterFondsSocial(Groupe $groupe, Cycle $cycle): void
    {
        $precedent = $groupe->cycles()->with('compteFondsSocial')->where('statut', StatutCycle::Cloture->value)
            ->orderByDesc('numero')->first();
        if ($precedent === null || $precedent->compteFondsSocial === null || $precedent->devise !== $cycle->devise) {
            return;
        }
        $solde = $this->moteur->soldeReel($precedent->compteFondsSocial);
        if (! $solde->estPositif()) {
            return;
        }

        $this->moteur->executer("report-fonds-social:{$precedent->id}:{$cycle->id}", 'tontine_report_fs', [
            LigneEcriture::debit($precedent->compteFondsSocial, $solde),
            LigneEcriture::credit($cycle->compteFondsSocial()->firstOrFail(), $solde),
        ], motif: "Report du fonds social du cycle {$precedent->numero}");
    }

    /**
     * @param  list<int>  $participants
     * @param  list<int>|null  $ordre
     */
    private function fixerTours(Cycle $cycle, array $participants, ?array $ordre): void
    {
        if ($cycle->mode_tours === null || $cycle->mode_tours === ModeTours::Enchere) {
            return; // AVEC : pas de tours ; enchère : attribué à chaque séance
        }

        if ($cycle->mode_tours === ModeTours::OrdreFixe) {
            $donne = array_map('intval', $ordre ?? []);
            $attendu = $participants;
            sort($donne);
            sort($attendu);
            if ($ordre === null || $donne !== $attendu) {
                throw new TontineRefusee('Ordre fixé : indiquez chaque participant une fois, dans l’ordre de passage.');
            }
            $ordre = array_map('intval', $ordre);
        } else {
            // Tirage au sort (Fisher-Yates avec random_int), une fois pour toutes.
            $ordre = $participants;
            for ($i = count($ordre) - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$ordre[$i], $ordre[$j]] = [$ordre[$j], $ordre[$i]];
            }
            $this->journal->enregistrer('tontines.tirage', $cycle, apres: ['ordre' => $ordre]);
        }

        foreach (array_values($ordre) as $i => $membreId) {
            (new Tour)->forceFill([
                'organisation_id' => $cycle->organisation_id, 'cycle_id' => $cycle->id, 'rang' => $i + 1, 'membre_id' => $membreId,
            ])->save();
        }
    }
}
