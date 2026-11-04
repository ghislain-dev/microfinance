<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Credit\Events\PretDecide;
use App\Domain\Epargne\Events\MouvementEpargneEnregistre;
use App\Domain\Notifications\Services\NotifierMembre;
use App\Domain\Tontines\Events\CycleDemarre;
use App\Domain\Tontines\Models\Seance;
use App\Domain\Tontines\Models\Tour;
use Carbon\CarbonImmutable;

/**
 * Avis push aux membres (ADR 022) : opération sur l'épargne, tour de tontine au démarrage du cycle,
 * décision sur un prêt. Rappels datés (échéances, veille du tour) : commande notifications:rappels.
 */
final class NotifierLesMembres
{
    public function __construct(private readonly NotifierMembre $notifier) {}

    public function mouvement(MouvementEpargneEnregistre $evenement): void
    {
        $mouvement = $evenement->mouvement->loadMissing(['compteEpargne', 'recu']);
        $compte = $mouvement->compteEpargne;
        $solde = $mouvement->recu?->solde_apres;

        $this->notifier->envoyer($mouvement->organisation_id, (int) $compte->getAttribute('membre_id'), "mouvement:{$mouvement->id}",
            $mouvement->libelleType(),
            "{$mouvement->libelleType()} de {$mouvement->montant->formate()} sur {$compte->numero}.".($solde !== null ? " Solde : {$solde->formate()}." : ''),
            ['type' => 'mouvement', 'compte_epargne_id' => (string) $compte->id]);
    }

    public function cycle(CycleDemarre $evenement): void
    {
        $cycle = $evenement->cycle->loadMissing('groupe');
        $dates = Seance::query()->where('cycle_id', $cycle->id)->pluck('date_prevue', 'rang');
        foreach (Tour::query()->where('cycle_id', $cycle->id)->get() as $tour) {
            $rang = (int) $tour->getAttribute('rang');
            $date = $dates[$rang] ?? null;
            $this->notifier->envoyer($cycle->organisation_id, (int) $tour->getAttribute('membre_id'), "tour:{$cycle->id}:{$tour->getAttribute('membre_id')}",
                'Votre tour de tontine',
                "{$cycle->groupe->nom}, cycle {$cycle->numero} : vous recevez le pot au rang {$rang}".($date !== null ? ', le '.CarbonImmutable::parse((string) $date)->format('d/m/Y') : '').'.',
                ['type' => 'tontine', 'cycle_id' => (string) $cycle->id]);
        }
    }

    public function pret(PretDecide $evenement): void
    {
        $pret = $evenement->pret;
        [$titre, $corps] = match ($evenement->decision) {
            'approuve' => ['Prêt approuvé', "Votre demande {$pret->numero} de {$pret->montant->formate()} est approuvée."],
            'rejete' => ['Demande de prêt rejetée', "Votre demande {$pret->numero} n’a pas été retenue. Renseignez-vous au guichet."],
            default => ['Prêt décaissé', "{$pret->montant->formate()} versés sur votre compte d’épargne (prêt {$pret->numero})."],
        };

        $this->notifier->envoyer($pret->organisation_id, (int) $pret->getAttribute('membre_id'), "pret:{$pret->id}:{$evenement->decision}",
            $titre, $corps, ['type' => 'pret', 'pret_id' => (string) $pret->id]);
    }
}
