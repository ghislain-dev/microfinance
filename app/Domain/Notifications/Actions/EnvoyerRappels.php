<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\StatutPret;
use App\Domain\Notifications\Services\NotifierMembre;
use App\Domain\Organisation\Models\Organisation;
use App\Domain\Tontines\Models\Seance;
use App\Domain\Tontines\Models\Tour;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\StatutSeance;
use Carbon\CarbonImmutable;

/**
 * Rappels datés (ADR 022) : échéance de prêt non payée N jours avant (N fixé par l'organisation, aucun rappel
 * sans valeur) ; veille de la séance où le membre reçoit le pot. Sans utilisateur connecté : organisation
 * filtrée explicitement ; une clé par rappel, donc la commande peut être relancée sans doublon.
 */
final class EnvoyerRappels
{
    public function __construct(private readonly NotifierMembre $notifier) {}

    public function executer(Organisation $organisation, CarbonImmutable $aujourdhui): void
    {
        $jours = $organisation->getAttribute('rappel_echeance_jours');
        if ($jours !== null) {
            $date = $aujourdhui->addDays((int) $jours);
            EcheancePret::query()->with('pret')->where('organisation_id', $organisation->id)->whereNull('payee_le')
                ->whereDate('date_echeance', $date->toDateString())
                ->whereHas('pret', fn ($q) => $q->where('statut', StatutPret::EnCours->value))
                ->each(function (EcheancePret $echeance) {
                    $this->notifier->envoyer($echeance->organisation_id, (int) $echeance->pret->getAttribute('membre_id'), "echeance:{$echeance->id}",
                        'Échéance de prêt',
                        "Prêt {$echeance->pret->numero} : échéance du {$echeance->date_echeance->format('d/m/Y')}, {$echeance->resteDu()->formate()} à payer.",
                        ['type' => 'pret', 'pret_id' => (string) $echeance->pret->id]);
                });
        }

        Seance::query()->with('cycle.groupe')->where('organisation_id', $organisation->id)->where('statut', StatutSeance::Prevue->value)
            ->whereDate('date_prevue', $aujourdhui->addDay()->toDateString())
            ->whereHas('cycle', fn ($q) => $q->where('statut', StatutCycle::EnCours->value))
            ->each(function (Seance $seance) {
                $tour = Tour::query()->where('cycle_id', $seance->cycle_id)->where('rang', $seance->getAttribute('rang'))->first();
                if ($tour === null) {
                    return; // enchère : le bénéficiaire n'est connu qu'à la séance
                }
                $this->notifier->envoyer($seance->organisation_id, (int) $tour->getAttribute('membre_id'), "veille-tour:{$seance->id}",
                    'Demain, vous recevez le pot',
                    "{$seance->cycle->groupe->nom} : séance du {$seance->date_prevue->format('d/m/Y')}, le pot vous est versé sur votre épargne.",
                    ['type' => 'tontine', 'cycle_id' => (string) $seance->cycle_id]);
            });
    }
}
