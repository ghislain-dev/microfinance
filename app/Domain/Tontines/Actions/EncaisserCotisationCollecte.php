<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Actions;

use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Models\Cotisation;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Seance;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\StatutSeance;
use App\Domain\Tontines\TontineRefusee;
use App\Domain\Tontines\TypeGroupe;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Montant;
use Illuminate\Support\Facades\DB;

/**
 * Cotisation collectée sur le terrain (ADR 022) : enregistrée pour la prochaine séance prévue du cycle ;
 * la feuille de séance la reprend comme déjà payée. Une seule cotisation par membre, séance et type.
 */
final class EncaisserCotisationCollecte
{
    public function __construct(private readonly MoteurFinancier $moteur) {}

    /**
     * @param  int|null  $parts  AVEC : nombre de parts achetées (le montant est alors parts × prix de la part)
     * @return int l'opération de la cotisation (ou du fonds social seul)
     */
    public function executer(User $collecteur, Compte $caisse, Cycle $cycle, Membre $membre, ?Montant $cotisation, ?int $parts, Montant $fondsSocial, string $cle): int
    {
        AutoriserDansOrganisation::autoriser($collecteur, 'collecter', $cycle);

        return DB::transaction(function () use ($caisse, $cycle, $membre, $cotisation, $parts, $fondsSocial, $cle) {
            $cycle = Cycle::query()->with(['groupe', 'compteFonds', 'compteFondsSocial'])->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            if ($cycle->statut !== StatutCycle::EnCours || ! $cycle->participants()->whereKey($membre->id)->exists()) {
                throw new TontineRefusee("{$membre->numero} ne participe pas à un cycle en cours de ce groupe.");
            }
            $seance = Seance::query()->where('cycle_id', $cycle->id)->where('statut', StatutSeance::Prevue->value)->orderBy('rang')->first()
                ?? throw new TontineRefusee('Plus aucune séance prévue dans ce cycle.');

            if ($cycle->groupe->type === TypeGroupe::Avec) {
                if ($parts === null || $parts < 1 || $parts > (int) $cycle->getAttribute('parts_max')) {
                    throw new TontineRefusee("De 1 à {$cycle->getAttribute('parts_max')} parts par séance.");
                }
                [$type, $montant] = ['parts', new Montant($cycle->montant_cotisation->centimes * $parts, $cycle->devise)];
            } else {
                $montant = $cotisation ?? Montant::zero($cycle->devise);
                if ($montant->devise !== $cycle->devise || $montant->estNegatif() || $montant->comparer($cycle->montant_cotisation) > 0) {
                    throw new TontineRefusee("Cotisation : au plus {$cycle->montant_cotisation->formate()}.");
                }
                [$type, $parts] = ['cotisation', null];
            }
            if ($fondsSocial->devise !== $cycle->devise || $fondsSocial->estNegatif() || $fondsSocial->comparer($cycle->cotisation_fonds_social) > 0) {
                throw new TontineRefusee("Fonds social : au plus {$cycle->cotisation_fonds_social->formate()}.");
            }
            if (! $montant->estPositif() && ! $fondsSocial->estPositif()) {
                throw new TontineRefusee('Rien à encaisser.');
            }
            if (Cotisation::query()->where('seance_id', $seance->id)->where('membre_id', $membre->id)->whereIn('type', [$type, 'fonds_social'])->exists()) {
                throw new TontineRefusee("Séance {$seance->getAttribute('rang')} déjà payée par {$membre->numero}.");
            }

            $premiere = null;
            foreach ([[$type, $montant, $cycle->compteFonds, $cle], ['fonds_social', $fondsSocial, $cycle->compteFondsSocial, "{$cle}:fs"]] as [$nature, $somme, $vers, $cleOperation]) {
                if (! $somme->estPositif()) {
                    continue;
                }
                $operation = $this->moteur->executer($cleOperation, "tontine_{$nature}", [LigneEcriture::debit($caisse, $somme), LigneEcriture::credit($vers, $somme)],
                    motif: "Collecte pour la séance {$seance->getAttribute('rang')}");
                (new Cotisation)->forceFill([
                    'organisation_id' => $cycle->organisation_id, 'seance_id' => $seance->id, 'membre_id' => $membre->id, 'type' => $nature,
                    'montant' => $somme->centimes, 'parts' => $nature === 'parts' ? $parts : null, 'operation_id' => $operation->id,
                ])->save();
                $premiere ??= $operation->id;
            }

            return (int) $premiere;
        });
    }
}
