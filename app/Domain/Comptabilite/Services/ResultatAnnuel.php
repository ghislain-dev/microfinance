<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Services;

use App\Domain\Comptabilite\Models\AffectationResultat;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\Services\LecturesComptables;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Carbon\CarbonImmutable;

/**
 * Résultat d'une année par devise (ADR 025) : produits moins charges, par date de valeur.
 */
final class ResultatAnnuel
{
    public function __construct(private readonly LecturesComptables $lectures) {}

    public function calculer(int $annee, Devise $devise): Montant
    {
        $totaux = $this->lectures->totauxParCompte($devise, CarbonImmutable::create($annee, 1, 1), CarbonImmutable::create($annee, 12, 31));
        $natures = Compte::query()->whereIn('id', array_keys($totaux))->pluck('nature', 'id');

        $resultat = 0;
        foreach ($totaux as $id => $t) {
            $resultat += match ($natures[$id] ?? null) {
                NatureCompte::Produit => $t['credit'] - $t['debit'],
                NatureCompte::Charge => -($t['debit'] - $t['credit']),
                default => 0,
            };
        }

        return new Montant($resultat, $devise);
    }

    /**
     * Résultat moins ce qui est déjà affecté ou proposé (en attente) pour cette année et cette devise.
     */
    public function restantAAffecter(int $annee, Devise $devise): Montant
    {
        $engage = (int) AffectationResultat::query()->where('annee', $annee)->where('devise', $devise->value)
            ->whereIn('statut', ['en_attente', 'validee'])->sum('montant');

        return $this->calculer($annee, $devise)->moins(new Montant($engage, $devise));
    }
}
