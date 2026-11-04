<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Actions;

use App\Domain\Comptabilite\ClotureRefusee;
use App\Domain\Comptabilite\Models\AffectationResultat;
use App\Domain\Comptabilite\Services\ResultatAnnuel;
use App\Domain\Finance\Models\ClotureComptable;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Montant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Le comptable propose d'affecter tout ou partie du résultat d'une année clôturée aux excédents à répartir.
 */
final class ProposerAffectation
{
    public function __construct(private readonly ResultatAnnuel $resultats) {}

    public function executer(User $comptable, int $annee, Montant $montant): AffectationResultat
    {
        AutoriserDansOrganisation::autoriser($comptable, 'affecter', AffectationResultat::class);
        $cloture = ClotureComptable::query()->max('date_fin');
        if ($cloture === null || (string) $cloture < "{$annee}-12-31") {
            throw new ClotureRefusee("Clôturez d'abord décembre {$annee} : l'affectation porte sur une année close.");
        }
        if (! $montant->estPositif()) {
            throw new ClotureRefusee('Montant à affecter positif attendu.');
        }

        try {
            return DB::transaction(function () use ($comptable, $annee, $montant) {
                $restant = $this->resultats->restantAAffecter($annee, $montant->devise);
                if ($montant->comparer($restant) > 0) {
                    throw new ClotureRefusee("Résultat {$annee} restant à affecter : {$restant->formate()}.");
                }

                $affectation = new AffectationResultat;
                $affectation->forceFill([
                    'organisation_id' => $comptable->organisation_id, 'annee' => $annee, 'devise' => $montant->devise,
                    'resultat' => $this->resultats->calculer($annee, $montant->devise), 'montant' => $montant,
                    'statut' => 'en_attente', 'propose_par_id' => $comptable->id,
                ])->save();

                return $affectation;
            });
        } catch (UniqueConstraintViolationException) {
            throw new ClotureRefusee('Une affectation de cette année attend déjà la validation du gérant.');
        }
    }
}
