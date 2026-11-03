<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Actions;

use App\Domain\Caisse\Billetage;
use App\Domain\Collecte\CollecteRefusee;
use App\Domain\Collecte\Models\VersementCollecte;
use App\Domain\Collecte\Services\CaisseCollecteur;
use App\Domain\Epargne\Services\RegleEpargne;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Devise;
use Illuminate\Support\Facades\DB;

/**
 * Le collecteur remet ses espèces au guichet (ADR 022) : le caissier compte (billetage), devise par devise.
 * Compté = théorique : transfert immédiat de la caisse du collecteur vers celle du guichet ; sinon le
 * versement attend la validation du gérant (comme un écart de clôture de caisse).
 */
final class EnregistrerVersement
{
    public function __construct(
        private readonly MoteurFinancier $moteur,
        private readonly RegleEpargne $regles,
        private readonly CaisseCollecteur $caisses,
    ) {}

    /**
     * @return list<VersementCollecte>
     */
    public function executer(User $caissier, User $collecteur, Billetage $billetage): array
    {
        AutoriserDansOrganisation::autoriser($caissier, 'recevoir', VersementCollecte::class);
        $session = $this->regles->sessionOuverte($caissier);
        $caisseCollecteur = $this->caisses->de($collecteur) ?? throw new CollecteRefusee("{$collecteur->name} n'a pas de caisse de collecte.");
        if ($collecteur->organisation_id !== $caissier->organisation_id) {
            throw new CollecteRefusee('Collecteur d’une autre organisation.');
        }

        return DB::transaction(function () use ($caissier, $collecteur, $billetage, $session, $caisseCollecteur) {
            if (VersementCollecte::query()->where('collecteur_id', $collecteur->id)->where('statut', 'ecart_a_justifier')->lockForUpdate()->exists()) {
                throw new CollecteRefusee('Un versement de ce collecteur attend déjà la validation de son écart.');
            }
            $versements = [];
            foreach (Devise::cases() as $devise) {
                $source = $caisseCollecteur->compte($devise);
                Compte::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
                $theorique = $this->moteur->soldeReel($source);
                $compte = $billetage->total($devise);
                if ($theorique->estZero() && $compte->estZero()) {
                    continue;
                }

                $versement = new VersementCollecte;
                $versement->forceFill([
                    'organisation_id' => $caissier->organisation_id, 'collecteur_id' => $collecteur->id, 'caissier_id' => $caissier->id,
                    'session_caisse_id' => $session->id, 'devise' => $devise, 'theorique' => $theorique, 'compte' => $compte,
                    'ecart' => $compte->moins($theorique), 'statut' => $compte->egal($theorique) ? 'valide' : 'ecart_a_justifier',
                    'billetage' => collect($billetage->lignes())->filter(fn (array $l) => $l['devise'] === $devise)
                        ->map(fn (array $l) => ['coupure' => $l['coupure'], 'nombre' => $l['nombre']])->values()->all(),
                ])->save();

                if ($versement->statut === 'valide') {
                    $operation = $this->moteur->executer("versement-collecte:{$versement->id}", 'versement_collecte', [
                        LigneEcriture::debit($session->caisse->compte($devise), $compte),
                        LigneEcriture::credit($source, $theorique),
                    ], motif: "Versement de {$collecteur->name}");
                    $versement->forceFill(['operation_id' => $operation->id])->save();
                }
                $versements[] = $versement;
            }

            return $versements;
        });
    }
}
