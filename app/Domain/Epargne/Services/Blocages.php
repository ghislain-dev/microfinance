<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Services;

use App\Domain\Epargne\Models\BlocageEpargne;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Support\Montant\Montant;

/**
 * Montants bloqués sur l'épargne par d'autres modules (garantie d'un prêt) : ils restent sur le compte
 * mais ne peuvent être ni retirés, ni virés, ni convertis tant que le blocage n'est pas levé.
 */
final class Blocages
{
    public function bloquer(CompteEpargne $compte, Montant $montant, string $reference, string $motif): BlocageEpargne
    {
        $blocage = new BlocageEpargne;
        $blocage->forceFill([
            'organisation_id' => $compte->organisation_id,
            'compte_epargne_id' => $compte->id,
            'devise' => $montant->devise, // avant le montant : MontantCast lit la devise
            'montant' => $montant,
            'reference' => $reference,
            'motif' => $motif,
        ])->save();

        return $blocage;
    }

    public function lever(string $reference): void
    {
        BlocageEpargne::query()->where('reference', $reference)->whereNull('leve_le')->first()
            ?->forceFill(['leve_le' => now()])->save();
    }

    /**
     * Montant encore bloqué pour cette référence (zéro si levé ou inconnu).
     */
    public function reste(string $reference, Montant $zero): Montant
    {
        $blocage = BlocageEpargne::query()->where('reference', $reference)->whereNull('leve_le')->first();

        return $blocage === null ? $zero : $blocage->montant;
    }

    /**
     * Libère une partie du blocage (ex. garantie prélevée pour rembourser) ; tout libéré : blocage levé.
     */
    public function reduire(string $reference, Montant $montant): void
    {
        $blocage = BlocageEpargne::query()->where('reference', $reference)->whereNull('leve_le')->lockForUpdate()->firstOrFail();
        $reste = $blocage->montant->moins($montant);
        $blocage->forceFill($reste->estPositif() ? ['montant' => $reste] : ['montant' => Montant::zero($reste->devise), 'leve_le' => now()])->save();
    }

    public function total(CompteEpargne $compte): Montant
    {
        return new Montant((int) BlocageEpargne::query()->where('compte_epargne_id', $compte->id)->whereNull('leve_le')->sum('montant'),
            $compte->type->devise);
    }

    /**
     * Ce qui peut sortir du compte : solde − solde minimum du type − blocages.
     */
    public function disponible(CompteEpargne $compte, Montant $solde): Montant
    {
        return $solde->moins($compte->type->solde_minimum)->moins($this->total($compte));
    }
}
