<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Recus;

use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\Models\Recu;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Organisation\Models\Organisation;
use App\Support\Montant\Montant;

/**
 * Numérote le reçu d'un mouvement d'épargne ou d'un remboursement de prêt (séquence par organisation, sans trou)
 * et commande son PDF.
 * Appelé dans la transaction de l'opération : le solde photographié est celui juste après.
 */
final class EmettreRecu
{
    public function __construct(private readonly MoteurFinancier $moteur) {}

    public function executer(MouvementEpargne $mouvement, CompteEpargne $compte): Recu
    {
        return $this->numeroter($compte->organisation_id, ['mouvement_epargne_id' => $mouvement->id], $this->moteur->soldeReel($compte->compte));
    }

    /**
     * Reçu d'un remboursement de prêt (module Crédit) : « solde après » = reste dû du prêt.
     */
    public function pourRemboursement(int $organisationId, int $remboursementId, Montant $resteDu): Recu
    {
        return $this->numeroter($organisationId, ['remboursement_pret_id' => $remboursementId], $resteDu);
    }

    /**
     * @param  array<string, int>  $objet  mouvement d'épargne ou remboursement de prêt
     */
    private function numeroter(int $organisationId, array $objet, Montant $soldeApres): Recu
    {
        // Verrou sur l'organisation : deux opérations simultanées ne prennent pas le même numéro.
        Organisation::query()->whereKey($organisationId)->lockForUpdate()->firstOrFail();
        $sequence = (int) Recu::withoutGlobalScopes()->where('organisation_id', $organisationId)->max('sequence') + 1;

        $recu = new Recu;
        $recu->forceFill([
            'organisation_id' => $organisationId,
            ...$objet,
            'sequence' => $sequence,
            'numero' => 'REC-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'devise' => $soldeApres->devise, // avant le solde : MontantCast lit la devise
            'solde_apres' => $soldeApres,
        ])->save();

        GenererRecuPdf::dispatch($recu->id);

        return $recu;
    }
}
