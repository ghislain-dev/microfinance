<?php

declare(strict_types=1);

namespace App\Domain\Rapports\Services;

use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Caisse\StatutSession;
use App\Domain\Collecte\Models\VersementCollecte;
use App\Domain\Collecte\Services\CaisseCollecteur;
use App\Domain\Comptabilite\Models\DemandeCloture;
use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Services\IndicateursCredit;
use App\Domain\Credit\StatutPret;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\DemandeDecaissement;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\StatutDemande;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\Services\LecturesComptables;
use App\Domain\Membres\Models\Membre;
use App\Domain\Membres\StatutMembre;
use App\Domain\Organisation\Acces\Droit;
use App\Domain\Organisation\Acces\Role;
use App\Domain\Tontines\Models\Aide;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Partage;
use App\Domain\Tontines\Models\Seance;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\StatutSeance;
use App\Models\User;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\Pourcentage;
use Carbon\CarbonImmutable;

/**
 * Indicateurs du tableau de bord (ADR 023), un bloc par domaine selon les droits de l'utilisateur.
 * Chaque bloc est une liste de lignes « libellé : valeur » déjà calculées ; la vue n'additionne rien.
 */
final class TableauDeBord
{
    public function __construct(
        private readonly LecturesComptables $lectures,
        private readonly IndicateursCredit $indicateurs,
        private readonly CaisseCollecteur $caisses,
    ) {}

    /**
     * @return array<string, list<string>> titre du bloc => lignes
     */
    public function pour(User $utilisateur): array
    {
        $blocs = [];
        if ($utilisateur->canAny([Droit::ComptaVoir->value, Droit::CaisseOperer->value])) {
            $blocs['Épargne'] = $this->epargne();
        }
        if ($utilisateur->can(Droit::RapportsCredit->value)) {
            $blocs['Crédit'] = $this->credit();
        }
        if ($utilisateur->canAny([Droit::RapportsCaisse->value, Droit::CaisseOperer->value])) {
            $blocs['Caisse et collecte'] = $this->caisse();
        }
        if ($utilisateur->can(Droit::MembresVoir->value)) {
            $blocs['Tontines et membres'] = $this->tontines();
        }

        return $blocs;
    }

    /**
     * @return list<string>
     */
    private function epargne(): array
    {
        $lignes = ['Comptes d’épargne : '.CompteEpargne::query()->count()];
        $aujourdhui = CarbonImmutable::today();
        foreach (Devise::cases() as $devise) {
            $comptes = Compte::query()->where('nature', NatureCompte::Epargne->value)->where('devise', $devise->value)->pluck('id')->all();
            $totaux = array_intersect_key($this->lectures->totauxParCompte($devise, null, null), array_flip($comptes));
            $total = new Montant(array_sum(array_column($totaux, 'credit')) - array_sum(array_column($totaux, 'debit')), $devise);
            $jour = fn (string $type) => new Montant((int) MouvementEpargne::query()->where('devise', $devise->value)->where('type', $type)
                ->where('created_at', '>=', $aujourdhui->startOfDay())->sum('montant'), $devise);
            $lignes[] = "Épargne totale {$devise->value} : {$total->formate()}";
            $lignes[] = "Dépôts du jour : {$jour('depot')->formate()} · retraits du jour : {$jour('retrait')->formate()}";
        }

        return $lignes;
    }

    /**
     * @return list<string>
     */
    private function credit(): array
    {
        $aujourdhui = CarbonImmutable::today();
        $lignes = [];
        foreach (Devise::cases() as $devise) {
            $par30 = $this->indicateurs->par(30, $devise);
            $par90 = $this->indicateurs->par(90, $devise);
            $lignes[] = "Encours {$devise->value} : {$par30->encours->formate()} · PAR 30 ".Pourcentage::formate($par30->tauxPb).' · PAR 90 '.Pourcentage::formate($par90->tauxPb);
        }
        $enCours = fn () => EcheancePret::query()->whereNull('payee_le')->whereHas('pret', fn ($q) => $q->where('statut', StatutPret::EnCours->value));
        $lignes[] = 'Échéances du jour : '.$enCours()->whereDate('date_echeance', $aujourdhui->toDateString())->count();
        $lignes[] = 'Prêts en retard : '.Pret::query()->whereIn('id', $enCours()->whereDate('date_echeance', '<', $aujourdhui->toDateString())->select('pret_id'))->count();

        return $lignes;
    }

    /**
     * @return list<string>
     */
    private function caisse(): array
    {
        $lignes = [
            'Sessions de caisse ouvertes : '.SessionCaisse::query()->where('statut', StatutSession::Ouverte->value)->count(),
            'Écarts à valider : '.(SessionCaisse::query()->whereIn('statut', [StatutSession::EcartAJustifier->value, StatutSession::OuvertureAValider->value])->count()
                + VersementCollecte::query()->where('statut', 'ecart_a_justifier')->count()),
        ];
        foreach (User::role(Role::Collecteur->value)->orderBy('name')->get() as $collecteur) {
            $caisse = $this->caisses->de($collecteur);
            if ($caisse === null) {
                continue;
            }
            $especes = array_map(fn (Devise $d) => $this->solde($caisse->compte($d))->formate(), Devise::cases());
            $lignes[] = "Espèces de {$collecteur->name} : ".implode(' · ', $especes);
        }

        return $lignes;
    }

    /**
     * @return list<string>
     */
    private function tontines(): array
    {
        return [
            'Membres actifs : '.Membre::query()->where('statut', StatutMembre::Actif->value)->count(),
            'Cycles de tontine en cours : '.Cycle::query()->where('statut', StatutCycle::EnCours->value)->count(),
            'Séances du jour : '.Seance::query()->where('statut', StatutSeance::Prevue->value)->whereDate('date_prevue', CarbonImmutable::today()->toDateString())->count(),
            'Prêts au comité : '.Pret::query()->where('statut', StatutPret::Soumis->value)->count()
                .' · au gérant : '.Pret::query()->where('statut', StatutPret::AttenteGerant->value)->count(),
            'Aides et partages au comité : '.(Aide::query()->where('statut', 'en_attente')->count() + Partage::query()->where('statut', 'brouillon')->count()),
            'Retraits et virements à valider : '.DemandeDecaissement::query()->where('statut', StatutDemande::EnAttente->value)->count()
                .' · clôture à valider : '.DemandeCloture::query()->where('statut', 'en_attente')->count(),
        ];
    }

    private function solde(Compte $compte): Montant
    {
        $t = $this->lectures->totauxParCompte($compte->devise, null, null)[$compte->id] ?? ['debit' => 0, 'credit' => 0];

        return new Montant($t['debit'] - $t['credit'], $compte->devise); // caisse : solde débiteur
    }
}
