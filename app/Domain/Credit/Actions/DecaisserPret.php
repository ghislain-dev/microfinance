<?php

declare(strict_types=1);

namespace App\Domain\Credit\Actions;

use App\Domain\Credit\CreditRefuse;
use App\Domain\Credit\Echeancier\CalculEcheancier;
use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Services\EligibiliteCredit;
use App\Domain\Credit\StatutPret;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Services\Blocages;
use App\Domain\Epargne\Services\RegleEpargne;
use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Models\Membre;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Montant;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Décaissement sur l'épargne du membre (gérant ou comptable, jamais l'auteur de la demande) : conditions
 * revérifiées, garantie bloquée, débit du compte de prêt / crédit de l'épargne, échéancier enregistré.
 */
final class DecaisserPret
{
    public function __construct(
        private readonly MoteurFinancier $moteur,
        private readonly OuvrirCompte $ouvrirCompte,
        private readonly EligibiliteCredit $eligibilite,
        private readonly Blocages $blocages,
        private readonly RegleEpargne $regles,
    ) {}

    public function executer(User $acteur, Pret $pret): Pret
    {
        return DB::transaction(function () use ($acteur, $pret) {
            $pret = Pret::query()->with('produit')->whereKey($pret->id)->lockForUpdate()->firstOrFail();
            AutoriserDansOrganisation::autoriser($acteur, 'decaisser', $pret); // statut lu sous verrou
            $membre = Membre::query()->with('agence')->findOrFail($pret->getAttribute('membre_id'));
            $compte = CompteEpargne::query()->with(['type', 'compte'])->findOrFail($pret->getAttribute('compte_epargne_id'));

            $this->eligibilite->exigerMembreEtCompte($membre, $compte, $pret->produit);
            $this->eligibilite->exigerPlafond($membre, $pret->produit, $pret->montant);

            // Garantie : jamais sous le pourcentage (arrondi au centime supérieur), prise sur l'épargne déjà
            // disponible, avant que le prêt n'y soit versé.
            $garantie = new Montant(BigInteger::of($pret->montant->centimes)->multipliedBy($pret->garantie_epargne_pb)
                ->dividedBy(10_000, RoundingMode::Up)->toInt(), $pret->devise);
            Compte::query()->whereKey($compte->compte_id)->lockForUpdate()->firstOrFail();
            $disponible = $this->blocages->disponible($compte, $this->moteur->soldeReel($compte->compte));
            if ($garantie->comparer($disponible) > 0) {
                throw new CreditRefuse("Épargne disponible insuffisante pour la garantie de {$garantie->formate()} ({$disponible->formate()} disponibles).");
            }

            $comptePret = $this->ouvrirCompte->executer($pret->numero, "Prêt {$pret->numero} — {$membre->nomComplet()}",
                NatureCompte::Pret, $pret->devise, $membre->agence);
            $comptePenalites = $this->ouvrirCompte->executer("{$pret->numero}-PEN", "Pénalités dues — prêt {$pret->numero}",
                NatureCompte::CreanceMembre, $pret->devise, $membre->agence);

            $operation = $this->moteur->executer("decaissement-pret:{$pret->id}", 'decaissement_credit', [
                LigneEcriture::debit($comptePret, $pret->montant),
                LigneEcriture::credit($compte->compte, $pret->montant),
            ], motif: "Décaissement du prêt {$pret->numero}");
            $this->regles->enregistrerMouvement($operation->id, $compte, 'decaissement_credit', $pret->montant);

            if ($garantie->estPositif()) {
                $this->blocages->bloquer($compte, $garantie, "pret:{$pret->id}", "Garantie du prêt {$pret->numero}");
            }

            $aujourdhui = CarbonImmutable::today();
            foreach (CalculEcheancier::calculer($pret->montant, $pret->taux_mensuel_pb, $pret->nombre_echeances,
                $pret->periodicite, $pret->methode, $aujourdhui) as $prevue) {
                $zero = Montant::zero($pret->devise);
                (new EcheancePret)->forceFill([
                    'organisation_id' => $pret->organisation_id, 'pret_id' => $pret->id, 'rang' => $prevue->rang,
                    'date_echeance' => $prevue->date->toDateString(), 'devise' => $pret->devise,
                    'capital' => $prevue->capital, 'interet' => $prevue->interet,
                    'capital_paye' => $zero, 'interet_paye' => $zero, 'penalite' => $zero, 'penalite_payee' => $zero,
                ])->save();
            }

            $pret->forceFill([
                'statut' => StatutPret::EnCours,
                'decaisse_par_id' => $acteur->id,
                'date_decaissement' => $aujourdhui->toDateString(),
                'garantie_epargne' => $garantie,
                'compte_pret_id' => $comptePret->id,
                'compte_penalites_id' => $comptePenalites->id,
                'operation_decaissement_id' => $operation->id,
            ])->save();

            return $pret;
        });
    }
}
