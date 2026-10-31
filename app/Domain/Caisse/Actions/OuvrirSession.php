<?php

declare(strict_types=1);

namespace App\Domain\Caisse\Actions;

use App\Domain\Caisse\Billetage;
use App\Domain\Caisse\Exceptions\SessionDejaOuverte;
use App\Domain\Caisse\Models\Caisse;
use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Caisse\MomentComptage;
use App\Domain\Caisse\Services\Comptage;
use App\Domain\Caisse\StatutSession;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Ouverture avec comptage (2026-10-31) : un écart bloque la session jusqu'à la validation du gérant.
 */
final class OuvrirSession
{
    public function __construct(private readonly Comptage $comptage) {}

    public function executer(User $caissier, Caisse $caisse, Billetage $billetage): SessionCaisse
    {
        AutoriserDansOrganisation::autoriser($caissier, 'operer', $caisse);

        try {
            return DB::transaction(function () use ($caissier, $caisse, $billetage) {
                Caisse::query()->whereKey($caisse->id)->lockForUpdate()->firstOrFail();
                $active = SessionCaisse::query()->whereNotNull('active_flag')
                    ->where(fn ($q) => $q->where('caisse_id', $caisse->id)->orWhere('caissier_id', $caissier->id))->exists();
                if ($active) {
                    throw new SessionDejaOuverte('Une session est déjà active sur cette caisse ou pour ce caissier.');
                }

                $session = new SessionCaisse(['statut' => StatutSession::Ouverte, 'ouverte_le' => now()]);
                $session->forceFill([
                    'organisation_id' => $caisse->organisation_id, 'caisse_id' => $caisse->id, 'caissier_id' => $caissier->id,
                ])->save();
                $session->setRelation('caisse', $caisse);

                if ($this->comptage->enregistrer($session, MomentComptage::Ouverture, $billetage) > 0) {
                    $session->forceFill(['statut' => StatutSession::OuvertureAValider])->save();
                }

                return $session->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            throw new SessionDejaOuverte('Une session est déjà active sur cette caisse ou pour ce caissier.');
        }
    }
}
