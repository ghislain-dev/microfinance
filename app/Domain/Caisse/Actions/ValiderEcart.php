<?php

declare(strict_types=1);

namespace App\Domain\Caisse\Actions;

use App\Domain\Caisse\Exceptions\SessionInvalide;
use App\Domain\Caisse\Models\EcartCaisse;
use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Caisse\StatutSession;
use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\MoteurFinancier;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Le gérant valide les écarts d'un comptage (2026-10-31) :
 * manquant → créance sur le caissier ; excédent → produit. Puis la session s'ouvre ou se ferme.
 */
final class ValiderEcart
{
    public function __construct(
        private readonly MoteurFinancier $moteur,
        private readonly OuvrirCompte $ouvrirCompte,
    ) {}

    public function executer(User $validateur, SessionCaisse $session, string $motif): SessionCaisse
    {
        $motif = trim($motif);
        if ($motif === '' || mb_strlen($motif) > 500) {
            throw new InvalidArgumentException('Motif obligatoire (500 caractères au plus).');
        }
        AutoriserDansOrganisation::autoriser($validateur, 'validerEcart', $session);

        return DB::transaction(function () use ($validateur, $session, $motif) {
            $session = SessionCaisse::query()->with(['caisse', 'caissier'])->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $moment = $session->momentEnAttente()
                ?? throw new SessionInvalide('Aucun écart en attente de validation sur cette session.');

            foreach ($session->ecarts()->where('moment', $moment->value)->get() as $ecart) {
                $this->comptabiliser($session, $ecart, $motif, $validateur);
            }

            $session->forceFill([
                ...($moment->value === 'ouverture'
                    ? ['statut' => StatutSession::Ouverte]
                    : ['statut' => StatutSession::Fermee, 'fermee_le' => now()]),
                'valide_par_id' => $validateur->id,
                'motif_validation' => $motif,
            ])->save();

            return $session;
        });
    }

    private function comptabiliser(SessionCaisse $session, EcartCaisse $ecart, string $motif, User $validateur): void
    {
        $caisse = $session->caisse->compte($ecart->devise);
        $montant = $ecart->ecart->estNegatif() ? $ecart->ecart->negation() : $ecart->ecart;

        if ($ecart->ecart->estNegatif()) {
            $caissier = $session->caissier;
            $creance = $this->ouvrirCompte->obtenir("CREANCE-CAISSIER-{$caissier->id}-{$ecart->devise->value}",
                "Créance sur {$caissier->name} (manquants de caisse)", NatureCompte::CreancePersonnel, $ecart->devise);
            $lignes = [LigneEcriture::debit($creance, $montant), LigneEcriture::credit($caisse, $montant)];
        } else {
            $produit = $this->ouvrirCompte->obtenir("PRODUIT-EXCEDENTS-CAISSE-{$ecart->devise->value}",
                "Excédents de caisse ({$ecart->devise->value})", NatureCompte::Produit, $ecart->devise);
            $lignes = [LigneEcriture::debit($caisse, $montant), LigneEcriture::credit($produit, $montant)];
        }

        $this->moteur->executer("ecart-caisse:{$ecart->id}", 'ecart_caisse', $lignes, motif: $motif, validateur: $validateur);
    }
}
