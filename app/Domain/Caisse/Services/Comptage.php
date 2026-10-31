<?php

declare(strict_types=1);

namespace App\Domain\Caisse\Services;

use App\Domain\Caisse\Billetage;
use App\Domain\Caisse\Models\EcartCaisse;
use App\Domain\Caisse\Models\LigneBilletage;
use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Caisse\MomentComptage;
use App\Domain\Finance\MoteurFinancier;
use App\Support\Montant\Devise;

/**
 * Enregistre un billetage et compare, devise par devise, le compté au solde réel du compte de caisse.
 */
final class Comptage
{
    public function __construct(private readonly MoteurFinancier $moteur) {}

    /**
     * @return int nombre de devises en écart
     */
    public function enregistrer(SessionCaisse $session, MomentComptage $moment, Billetage $billetage): int
    {
        foreach ($billetage->lignes() as $ligne) {
            (new LigneBilletage([
                'moment' => $moment->value, 'devise' => $ligne['devise']->value,
                'coupure' => $ligne['coupure'], 'nombre' => $ligne['nombre'],
            ]))->forceFill(['organisation_id' => $session->organisation_id, 'session_caisse_id' => $session->id])->save();
        }

        $ecarts = 0;
        foreach (Devise::cases() as $devise) {
            $theorique = $this->moteur->soldeReel($session->caisse->compte($devise));
            $compte = $billetage->total($devise);
            $ecart = $compte->moins($theorique);
            if ($ecart->estZero()) {
                continue;
            }

            (new EcartCaisse(['moment' => $moment, 'devise' => $devise, 'theorique' => $theorique, 'compte' => $compte, 'ecart' => $ecart]))
                ->forceFill(['organisation_id' => $session->organisation_id, 'session_caisse_id' => $session->id])->save();
            $ecarts++;
        }

        return $ecarts;
    }
}
