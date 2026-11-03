<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Actions;

use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Epargne\Services\RegleEpargne;
use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Models\Cotisation;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\DetteTontine;
use App\Domain\Tontines\Models\PresenceTontine;
use App\Domain\Tontines\Models\Seance;
use App\Domain\Tontines\Models\Tour;
use App\Domain\Tontines\ModeTours;
use App\Domain\Tontines\Presence;
use App\Domain\Tontines\Services\Dettes;
use App\Domain\Tontines\Services\EpargneDuMembre;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\StatutSeance;
use App\Domain\Tontines\TontineRefusee;
use App\Domain\Tontines\TypeGroupe;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantInvalide;
use Illuminate\Support\Facades\DB;

/**
 * Saisie d'une séance en une seule action (skill §5) : présences, cotisations (ou parts AVEC), fonds social,
 * règlements de dettes, pénalités automatiques, puis versement du tour (rotative). Chaque montant passe par
 * le MoteurFinancier avec la clé « seance:{id}:membre:{id}:{type} ».
 */
final class SaisirSeance
{
    public function __construct(
        private readonly MoteurFinancier $moteur,
        private readonly RegleEpargne $regles,
        private readonly OuvrirCompte $ouvrirCompte,
        private readonly Dettes $dettes,
        private readonly EpargneDuMembre $epargne,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lignes  membre_id => presence, cotisation, parts, fonds_social, reglement
     * @param  array{membre_id: int, remise: string}|null  $enchere  bénéficiaire et remise (mode enchère)
     */
    public function executer(User $operateur, Seance $seance, array $lignes, ?array $enchere = null): Seance
    {
        AutoriserDansOrganisation::autoriser($operateur, 'saisir', $seance);
        $session = $this->regles->sessionOuverte($operateur);

        return DB::transaction(function () use ($operateur, $seance, $lignes, $enchere, $session) {
            $seance = Seance::query()->whereKey($seance->id)->lockForUpdate()->firstOrFail();
            if ($seance->statut === StatutSeance::Tenue) {
                return $seance; // double envoi : déjà saisie, rien de plus
            }
            $cycle = Cycle::query()->with(['groupe', 'compteFonds', 'compteFondsSocial', 'compteCreances'])->findOrFail($seance->cycle_id);
            $this->controlerOrdre($cycle, $seance);
            $participants = $cycle->participants()->get()->keyBy('id');
            if (collect(array_keys($lignes))->map(fn ($id) => (int) $id)->sort()->values()->all() !== $participants->keys()->sort()->values()->all()) {
                throw new TontineRefusee('La feuille de séance doit comporter chaque participant, une seule fois.');
            }

            $caisse = $session->caisse->compte($cycle->devise);
            foreach ($lignes as $membreId => $ligne) {
                $this->saisirMembre($cycle, $seance, $participants[(int) $membreId], $ligne, $caisse);
            }
            if ($cycle->groupe->type === TypeGroupe::Rotative) {
                $this->verserTour($cycle, $seance, $enchere, $participants->count());
            }

            $seance->forceFill([
                'statut' => StatutSeance::Tenue, 'tenue_le' => now(),
                'saisie_par_id' => $operateur->id, 'session_caisse_id' => $session->id,
            ])->save();

            return $seance;
        });
    }

    private function controlerOrdre(Cycle $cycle, Seance $seance): void
    {
        if ($cycle->statut !== StatutCycle::EnCours) {
            throw new TontineRefusee('Le cycle n’est pas en cours.');
        }
        $avantNonTenue = Seance::query()->where('cycle_id', $cycle->id)->where('rang', '<', $seance->rang)
            ->where('statut', StatutSeance::Prevue->value)->exists();
        if ($avantNonTenue) {
            throw new TontineRefusee('Les séances se tiennent dans l’ordre : saisissez d’abord les séances précédentes.');
        }
    }

    /**
     * @param  array<string, mixed>  $ligne
     */
    private function saisirMembre(Cycle $cycle, Seance $seance, Membre $membre, array $ligne, Compte $caisse): void
    {
        $presence = Presence::from((string) ($ligne['presence'] ?? ''));
        $fondsSocial = $this->montant($ligne['fonds_social'] ?? '0', $cycle);
        $reglement = $this->montant($ligne['reglement'] ?? '0', $cycle);
        $impaye = false;

        // Règlement de dettes antérieures (avant les nouvelles dettes de la séance).
        if ($reglement->comparer($this->dettes->du($cycle, $membre)) > 0) {
            throw new TontineRefusee("{$membre->numero} : le règlement dépasse sa dette ({$this->dettes->du($cycle, $membre)->formate()}).");
        }

        if ($cycle->groupe->type === TypeGroupe::Avec) {
            $parts = filter_var($ligne['parts'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $cycle->parts_max]]);
            if ($parts === false) {
                throw new TontineRefusee("{$membre->numero} : de 0 à {$cycle->parts_max} parts par séance.");
            }
            $prepaye = $this->dejaCollecte($seance, $membre, 'parts');
            if ($prepaye !== null) {
                $parts = (int) $prepaye->getAttribute('parts'); // collecté par le collecteur : pas de second encaissement
            } else {
                $montantParts = new Montant($cycle->montant_cotisation->centimes * $parts, $cycle->devise);
                $this->encaisser($seance, $membre, 'parts', $montantParts, $caisse, $cycle->compteFonds, $parts);
            }
            $impaye = $parts < $cycle->parts_min; // pas de dette de parts : épargne volontaire
        } else {
            $prepaye = $this->dejaCollecte($seance, $membre, 'cotisation');
            $cotisation = $prepaye !== null ? new Montant((int) $prepaye->getAttribute('montant'), $cycle->devise) : $this->montant($ligne['cotisation'] ?? '0', $cycle);
            if ($cotisation->comparer($cycle->montant_cotisation) > 0) {
                throw new TontineRefusee("{$membre->numero} : la cotisation dépasse {$cycle->montant_cotisation->formate()}.");
            }
            if ($prepaye === null) {
                $this->encaisser($seance, $membre, 'cotisation', $cotisation, $caisse, $cycle->compteFonds);
            }
            $reste = $cycle->montant_cotisation->moins($cotisation);
            $this->constater($cycle, $seance, $membre, 'cotisation', $reste, $cycle->compteFonds);
            $impaye = $reste->estPositif();
        }

        $fondsSocialCollecte = $this->dejaCollecte($seance, $membre, 'fonds_social');
        if ($fondsSocialCollecte !== null) {
            $fondsSocial = new Montant((int) $fondsSocialCollecte->getAttribute('montant'), $cycle->devise);
        }
        if ($fondsSocial->comparer($cycle->cotisation_fonds_social) > 0) {
            throw new TontineRefusee("{$membre->numero} : le fonds social dépasse {$cycle->cotisation_fonds_social->formate()}.");
        }
        if ($fondsSocialCollecte === null) {
            $this->encaisser($seance, $membre, 'fonds_social', $fondsSocial, $caisse, $cycle->compteFondsSocial);
        }
        $this->constater($cycle, $seance, $membre, 'fonds_social', $cycle->cotisation_fonds_social->moins($fondsSocial), $cycle->compteFondsSocial);

        // Pénalités automatiques : dettes envers le cycle (elles lui reviennent, décision du 2026-10-31).
        $this->constater($cycle, $seance, $membre, 'penalite_absence', $presence === Presence::Absent ? $cycle->penalite_absence : null, $cycle->compteFonds);
        $this->constater($cycle, $seance, $membre, 'penalite_retard', $presence === Presence::Retard ? $cycle->penalite_retard : null, $cycle->compteFonds);
        $this->constater($cycle, $seance, $membre, 'penalite_impaye', $impaye ? $cycle->penalite_impaye : null, $cycle->compteFonds);

        $this->encaisser($seance, $membre, 'reglement', $reglement, $caisse, $cycle->compteCreances);

        (new PresenceTontine)->forceFill([
            'organisation_id' => $cycle->organisation_id, 'seance_id' => $seance->id, 'membre_id' => $membre->id, 'presence' => $presence->value,
        ])->save();
    }

    /**
     * Cotisation déjà encaissée par un collecteur pour cette séance (ADR 022) : la feuille la reprend telle quelle.
     */
    private function dejaCollecte(Seance $seance, Membre $membre, string $type): ?Cotisation
    {
        return Cotisation::query()->where('seance_id', $seance->id)->where('membre_id', $membre->id)->where('type', $type)->first();
    }

    /**
     * Espèces reçues : débit caisse, crédit du compte du cycle visé.
     */
    private function encaisser(Seance $seance, Membre $membre, string $type, Montant $montant, Compte $caisse, Compte $vers, ?int $parts = null): void
    {
        if ($montant->estZero() && $parts === null) {
            return;
        }
        $operationId = null;
        if ($montant->estPositif()) {
            $operationId = $this->moteur->executer("seance:{$seance->id}:membre:{$membre->id}:{$type}", "tontine_{$type}",
                [LigneEcriture::debit($caisse, $montant), LigneEcriture::credit($vers, $montant)])->id;
        }
        if ($operationId === null) {
            return; // 0 part : rien d'encaissé, la pénalité est constatée à part
        }

        (new Cotisation)->forceFill([
            'organisation_id' => $seance->organisation_id, 'seance_id' => $seance->id, 'membre_id' => $membre->id,
            'type' => $type, 'montant' => $montant->centimes, 'parts' => $parts, 'operation_id' => $operationId,
        ])->save();
    }

    /**
     * Dette constatée : débit des dettes des membres, crédit du compte du cycle visé.
     */
    private function constater(Cycle $cycle, Seance $seance, Membre $membre, string $nature, ?Montant $montant, Compte $vers): void
    {
        if ($montant === null || ! $montant->estPositif()) {
            return;
        }
        $operation = $this->moteur->executer("seance:{$seance->id}:membre:{$membre->id}:dette_{$nature}", 'tontine_dette',
            [LigneEcriture::debit($cycle->compteCreances, $montant), LigneEcriture::credit($vers, $montant)]);

        (new DetteTontine)->forceFill([
            'organisation_id' => $cycle->organisation_id, 'cycle_id' => $cycle->id, 'seance_id' => $seance->id,
            'membre_id' => $membre->id, 'nature' => $nature, 'montant' => $montant->centimes, 'operation_id' => $operation->id,
        ])->save();
    }

    /**
     * Pot complet (participants × cotisation) versé sur l'épargne du bénéficiaire (décisions du 2026-10-31) ;
     * à l'enchère, la remise va en produit de l'organisation.
     *
     * @param  array{membre_id: int, remise: string}|null  $enchere
     */
    private function verserTour(Cycle $cycle, Seance $seance, ?array $enchere, int $nombre): void
    {
        $remise = Montant::zero($cycle->devise);
        $pot = new Montant($cycle->montant_cotisation->centimes * $nombre, $cycle->devise);

        if ($cycle->mode_tours === ModeTours::Enchere) {
            if ($enchere === null) {
                throw new TontineRefusee('Enchère : indiquez le bénéficiaire et sa remise.');
            }
            $remise = $this->montant($enchere['remise'], $cycle);
            if ($remise->comparer($pot) >= 0) {
                throw new TontineRefusee('La remise doit être inférieure au pot.');
            }
            if (! $cycle->participants()->whereKey($enchere['membre_id'])->exists()
                || Tour::query()->where('cycle_id', $cycle->id)->where('membre_id', $enchere['membre_id'])->exists()) {
                throw new TontineRefusee('Ce membre ne participe pas au cycle ou a déjà reçu son tour.');
            }
            (new Tour)->forceFill([
                'organisation_id' => $cycle->organisation_id, 'cycle_id' => $cycle->id, 'rang' => $seance->rang,
                'membre_id' => (int) $enchere['membre_id'], 'remise' => $remise->centimes,
            ])->save();
        }

        $beneficiaireId = Tour::query()->where('cycle_id', $cycle->id)->where('rang', $seance->rang)->value('membre_id');
        $compte = $this->epargne->exiger((int) $beneficiaireId, $cycle->devise, 'bénéficiaire du tour');

        $verse = $pot->moins($remise);
        $lignes = [LigneEcriture::debit($cycle->compteFonds, $pot), LigneEcriture::credit($compte->compte, $verse)];
        if ($remise->estPositif()) {
            $lignes[] = LigneEcriture::credit($this->ouvrirCompte->obtenir("PRODUIT-ENCHERES-TONTINE-{$cycle->devise->value}",
                "Remises d’enchères de tontine ({$cycle->devise->value})", NatureCompte::Produit, $cycle->devise), $remise);
        }
        $operation = $this->moteur->executer("seance:{$seance->id}:tour", 'tontine_tour', $lignes);
        $this->regles->enregistrerMouvement($operation->id, $compte, 'tontine_tour', $verse,
            session: SessionCaisse::query()->find($seance->getAttribute('session_caisse_id')));
    }

    private function montant(mixed $saisie, Cycle $cycle): Montant
    {
        try {
            return Montant::depuisSaisie((string) $saisie, $cycle->devise);
        } catch (MontantInvalide) {
            throw new TontineRefusee("Montant invalide : « {$saisie} ».");
        }
    }
}
