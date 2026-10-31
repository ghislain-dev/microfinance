<?php

declare(strict_types=1);

namespace App\Domain\Caisse\Actions;

use App\Domain\Caisse\Billetage;
use App\Domain\Caisse\Exceptions\SessionInvalide;
use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Caisse\MomentComptage;
use App\Domain\Caisse\Services\Comptage;
use App\Domain\Caisse\StatutSession;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Support\Facades\DB;

/**
 * Clôture par le caissier de la session : billetage comparé au solde théorique ; écart = bloquant.
 */
final class CloturerSession
{
    public function __construct(private readonly Comptage $comptage) {}

    public function executer(User $caissier, SessionCaisse $session, Billetage $billetage): SessionCaisse
    {
        AutoriserDansOrganisation::autoriser($caissier, 'cloturer', $session);

        return DB::transaction(function () use ($session, $billetage) {
            $session = SessionCaisse::query()->with('caisse')->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->statut !== StatutSession::Ouverte) {
                throw new SessionInvalide("Session {$session->statut->libelle()} : clôture impossible.");
            }

            $ecarts = $this->comptage->enregistrer($session, MomentComptage::Cloture, $billetage);
            $session->forceFill($ecarts > 0
                ? ['statut' => StatutSession::EcartAJustifier]
                : ['statut' => StatutSession::Fermee, 'fermee_le' => now()])->save();

            return $session;
        });
    }
}
