<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Actions;

use App\Domain\Comptabilite\Models\AffectationResultat;
use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\MoteurFinancier;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Le gérant valide (débit du résultat en instance d'affectation, crédit des excédents à répartir) ou rejette ;
 * jamais l'auteur de la proposition. L'opération est datée du jour : l'année affectée est déjà clôturée.
 */
final class DeciderAffectation
{
    public function __construct(private readonly MoteurFinancier $moteur, private readonly OuvrirCompte $ouvrirCompte) {}

    public function valider(User $gerant, AffectationResultat $affectation, string $motif): AffectationResultat
    {
        $motif = $this->motif($motif);

        return DB::transaction(function () use ($gerant, $affectation, $motif) {
            $affectation = AffectationResultat::query()->whereKey($affectation->id)->lockForUpdate()->firstOrFail();
            AutoriserDansOrganisation::autoriser($gerant, 'validerAffectation', $affectation);
            $devise = $affectation->devise;
            $resultat = $this->ouvrirCompte->obtenir("RESULTAT-NET-{$devise->value}", "Résultat net en instance d’affectation ({$devise->value})",
                NatureCompte::Resultat, $devise);
            $excedents = $this->ouvrirCompte->obtenir("EXCEDENTS-A-REPARTIR-{$devise->value}", "Excédents à répartir ({$devise->value})",
                NatureCompte::FondsPropres, $devise);

            $operation = $this->moteur->executer("affectation-resultat:{$affectation->id}", 'affectation_resultat', [
                LigneEcriture::debit($resultat, $affectation->montant),
                LigneEcriture::credit($excedents, $affectation->montant),
            ], motif: "Affectation du résultat {$affectation->annee} : {$motif}", validateur: $gerant);

            $affectation->forceFill(['statut' => 'validee', 'decide_par_id' => $gerant->id, 'motif_decision' => $motif, 'operation_id' => $operation->id])->save();

            return $affectation;
        });
    }

    public function rejeter(User $gerant, AffectationResultat $affectation, string $motif): AffectationResultat
    {
        $motif = $this->motif($motif);
        AutoriserDansOrganisation::autoriser($gerant, 'validerAffectation', $affectation);
        $affectation->forceFill(['statut' => 'rejetee', 'decide_par_id' => $gerant->id, 'motif_decision' => $motif])->save();

        return $affectation;
    }

    private function motif(string $motif): string
    {
        $motif = trim($motif);
        if ($motif === '' || mb_strlen($motif) > 500) {
            throw new InvalidArgumentException('Motif obligatoire (500 caractères au plus).');
        }

        return $motif;
    }
}
