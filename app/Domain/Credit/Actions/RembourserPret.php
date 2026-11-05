<?php

declare(strict_types=1);

namespace App\Domain\Credit\Actions;

use App\Domain\Caisse\Models\Caisse;
use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Credit\CreditRefuse;
use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\Remboursement;
use App\Domain\Credit\ModeRemboursement;
use App\Domain\Credit\StatutPret;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Recus\EmettreRecu;
use App\Domain\Epargne\Services\Blocages;
use App\Domain\Epargne\Services\RegleEpargne;
use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Montant;
use Illuminate\Support\Facades\DB;

/**
 * Remboursement manuel (caissier ou gérant), en espèces ou depuis l'épargne. Imputation échéance par
 * échéance, la plus ancienne d'abord, et dans chacune : pénalités → intérêts → capital (skill §4 et §6).
 * Un montant au-delà des échéances échues rembourse par anticipation les suivantes, intérêts prévus compris.
 */
final class RembourserPret
{
    public function __construct(
        private readonly MoteurFinancier $moteur,
        private readonly RegleEpargne $regles,
        private readonly OuvrirCompte $ouvrirCompte,
        private readonly Blocages $blocages,
        private readonly EmettreRecu $recus,
    ) {}

    /**
     * @param  Caisse|null  $caisseCollecteur  remboursement en espèces encaissé sur le terrain par le collecteur (ADR 022)
     */
    public function executer(User $operateur, Pret $pret, Montant $montant, ModeRemboursement $mode, string $cle, ?Caisse $caisseCollecteur = null): Remboursement
    {
        AutoriserDansOrganisation::autoriser($operateur, $caisseCollecteur === null ? 'rembourser' : 'collecter', $pret);
        $session = $mode === ModeRemboursement::Especes && $caisseCollecteur === null ? $this->regles->sessionOuverte($operateur) : null;

        if (($deja = $this->moteur->dejaEnregistree($cle)) !== null) {
            return Remboursement::query()->where('operation_id', $deja->id)->firstOrFail();
        }
        if ($montant->devise !== $pret->devise || ! $montant->estPositif()) {
            throw new CreditRefuse("Montant attendu : positif, en {$pret->devise->value}.");
        }

        return $this->effectuer($pret, $montant, $mode, $cle, $session, $caisseCollecteur);
    }

    /**
     * Remboursement prélevé sur la garantie bloquée (ADR 021, défaut de paiement). Appelé seulement par
     * DeciderSaisieGarantie, qui a déjà vérifié la décision du gérant et la validation du comptable, et libéré
     * la part de garantie correspondante.
     */
    public function prelevementSurGarantie(Pret $pret, Montant $montant, string $cle): Remboursement
    {
        if (($deja = $this->moteur->dejaEnregistree($cle)) !== null) {
            return Remboursement::query()->where('operation_id', $deja->id)->firstOrFail();
        }

        return $this->effectuer($pret, $montant, ModeRemboursement::Epargne, $cle, null, null);
    }

    private function effectuer(Pret $pret, Montant $montant, ModeRemboursement $mode, string $cle, ?SessionCaisse $session, ?Caisse $caisseCollecteur): Remboursement
    {
        return DB::transaction(function () use ($pret, $montant, $mode, $cle, $session, $caisseCollecteur) {
            $pret = Pret::query()->with(['comptePret', 'comptePenalites'])->whereKey($pret->id)->lockForUpdate()->firstOrFail();
            if ($pret->statut !== StatutPret::EnCours) {
                throw new CreditRefuse("Le prêt {$pret->numero} n'est pas en cours ({$pret->statut->libelle()}).");
            }
            $echeances = EcheancePret::query()->where('pret_id', $pret->id)->whereNull('payee_le')->orderBy('rang')->get();
            $du = $echeances->reduce(fn (Montant $total, EcheancePret $e) => $total->plus($e->resteDu()), Montant::zero($pret->devise));
            if ($montant->comparer($du) > 0) {
                throw new CreditRefuse("Le prêt {$pret->numero} ne doit plus que {$du->formate()}.");
            }

            [$penalites, $interets, $capital] = $this->imputer($echeances->all(), $montant);

            $compteEpargne = null;
            if ($mode === ModeRemboursement::Epargne) {
                $compteEpargne = CompteEpargne::query()->with(['type', 'compte'])->findOrFail($pret->getAttribute('compte_epargne_id'));
                Compte::query()->whereKey($compteEpargne->compte_id)->lockForUpdate()->firstOrFail();
                $this->regles->exigerSoldeMinimum($this->moteur->soldeReel($compteEpargne->compte)->moins($montant), $compteEpargne);
                $source = $compteEpargne->compte;
            } else {
                $source = ($caisseCollecteur ?? $session?->caisse)?->compte($pret->devise) ?? throw new CreditRefuse('Session de caisse requise.');
            }

            $lignes = [LigneEcriture::debit($source, $montant)];
            if ($penalites->estPositif()) {
                $lignes[] = LigneEcriture::credit($pret->comptePenalites ?? throw new CreditRefuse('Compte de pénalités absent.'), $penalites);
            }
            if ($interets->estPositif()) {
                $lignes[] = LigneEcriture::credit($this->ouvrirCompte->obtenir("PRODUIT-INTERETS-PRET-{$pret->devise->value}",
                    "Intérêts sur prêts ({$pret->devise->value})", NatureCompte::Produit, $pret->devise), $interets);
            }
            if ($capital->estPositif()) {
                $lignes[] = LigneEcriture::credit($pret->comptePret ?? throw new CreditRefuse('Compte de prêt absent.'), $capital);
            }
            $operation = $this->moteur->executer($cle, 'remboursement_credit', $lignes, motif: "Remboursement du prêt {$pret->numero}");

            foreach ($echeances as $echeance) {
                if ($echeance->isDirty()) {
                    $echeance->save();
                }
            }
            if ($compteEpargne !== null) {
                $this->regles->enregistrerMouvement($operation->id, $compteEpargne, 'remboursement_credit', $montant);
            }

            $remboursement = new Remboursement;
            $remboursement->forceFill([
                'organisation_id' => $pret->organisation_id,
                'pret_id' => $pret->id,
                'operation_id' => $operation->id,
                'mode' => $mode,
                'devise' => $pret->devise, // avant les montants : MontantCast lit la devise
                'montant' => $montant,
                'penalites' => $penalites,
                'interets' => $interets,
                'capital' => $capital,
                'session_caisse_id' => $session?->id,
                'auteur_id' => auth()->id(),
            ])->save();

            $this->recus->pourRemboursement($pret->organisation_id, $remboursement->id, $du->moins($montant));

            if ($montant->egal($du)) {
                $pret->forceFill(['statut' => StatutPret::Solde, 'solde_le' => now()])->save();
                $this->blocages->lever("pret:{$pret->id}");
            }

            return $remboursement;
        });
    }

    /**
     * Répartit le montant sur les échéances (modifiées en mémoire) ; renvoie [pénalités, intérêts, capital].
     *
     * @param  list<EcheancePret>  $echeances
     * @return array{Montant, Montant, Montant}
     */
    private function imputer(array $echeances, Montant $montant): array
    {
        $zero = Montant::zero($montant->devise);
        [$penalites, $interets, $capital] = [$zero, $zero, $zero];
        $reste = $montant;

        foreach ($echeances as $echeance) {
            foreach (['penalite', 'interet', 'capital'] as $part) {
                $du = $echeance->getAttribute($part)->moins($echeance->getAttribute($part === 'penalite' ? 'penalite_payee' : "{$part}_paye"));
                $paye = $reste->comparer($du) < 0 ? $reste : $du;
                if (! $paye->estPositif()) {
                    continue;
                }
                $colonne = $part === 'penalite' ? 'penalite_payee' : "{$part}_paye";
                $echeance->setAttribute($colonne, $echeance->getAttribute($colonne)->plus($paye));
                $reste = $reste->moins($paye);
                match ($part) {
                    'penalite' => $penalites = $penalites->plus($paye),
                    'interet' => $interets = $interets->plus($paye),
                    'capital' => $capital = $capital->plus($paye),
                };
            }
            if ($echeance->resteDu()->estZero()) {
                $echeance->setAttribute('payee_le', now());
            }
            if ($reste->estZero()) {
                break;
            }
        }

        return [$penalites, $interets, $capital];
    }
}
