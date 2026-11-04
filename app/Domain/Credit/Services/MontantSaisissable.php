<?php

declare(strict_types=1);

namespace App\Domain\Credit\Services;

use App\Domain\Credit\CreditRefuse;
use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\StatutPret;
use App\Domain\Epargne\Services\Blocages;
use App\Support\Montant\Montant;
use App\Support\Temps\Horloge;

/**
 * Part de la garantie qui peut être prélevée : le plus petit de la garantie encore bloquée et de l'échu,
 * une fois le délai de retard du produit dépassé (ADR 021).
 */
final class MontantSaisissable
{
    public function __construct(private readonly Blocages $blocages) {}

    public function pour(Pret $pret): Montant
    {
        if ($pret->statut !== StatutPret::EnCours) {
            throw new CreditRefuse("Le prêt {$pret->numero} n'est pas en cours.");
        }
        $delai = $pret->getAttribute('delai_saisie_garantie_jours');
        if ($delai === null) {
            throw new CreditRefuse('Le produit de ce prêt ne prévoit pas de prélèvement de la garantie.');
        }
        $aujourdhui = Horloge::aujourdhui($pret->organisation_id);
        $echues = EcheancePret::query()->where('pret_id', $pret->id)->whereNull('payee_le')
            ->whereDate('date_echeance', '<=', $aujourdhui->toDateString())->orderBy('rang')->get();
        $plusAncienne = $echues->first();
        if ($plusAncienne === null || ! $plusAncienne->date_echeance->lt($aujourdhui->subDays((int) $delai))) {
            throw new CreditRefuse("Prélèvement possible après {$delai} jours de retard seulement.");
        }

        $zero = Montant::zero($pret->devise);
        $echu = $echues->reduce(fn (Montant $t, EcheancePret $e) => $t->plus($e->resteDu()), $zero);
        $garantie = $this->blocages->reste("pret:{$pret->id}", $zero);
        if (! $garantie->estPositif()) {
            throw new CreditRefuse('Aucune garantie bloquée sur ce prêt.');
        }

        return $garantie->comparer($echu) < 0 ? $garantie : $echu;
    }
}
