<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Recus;

use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\Models\Recu;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Organisation\Models\Organisation;

/**
 * Numérote le reçu d'un mouvement (séquence par organisation, sans trou) et commande son PDF.
 * Appelé dans la transaction de l'opération : le solde photographié est celui juste après.
 */
final class EmettreRecu
{
    public function __construct(private readonly MoteurFinancier $moteur) {}

    public function executer(MouvementEpargne $mouvement, CompteEpargne $compte): Recu
    {
        // Verrou sur l'organisation : deux opérations simultanées ne prennent pas le même numéro.
        Organisation::query()->whereKey($compte->organisation_id)->lockForUpdate()->firstOrFail();
        $sequence = (int) Recu::withoutGlobalScopes()->where('organisation_id', $compte->organisation_id)->max('sequence') + 1;

        $recu = new Recu;
        $recu->forceFill([
            'organisation_id' => $compte->organisation_id,
            'mouvement_epargne_id' => $mouvement->id,
            'sequence' => $sequence,
            'numero' => 'REC-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'devise' => $mouvement->devise,
            'solde_apres' => $this->moteur->soldeReel($compte->compte),
        ])->save();

        GenererRecuPdf::dispatch($recu->id);

        return $recu;
    }
}
