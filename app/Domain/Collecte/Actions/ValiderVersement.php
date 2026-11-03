<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Actions;

use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Collecte\Models\VersementCollecte;
use App\Domain\Collecte\Services\CaisseCollecteur;
use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\MoteurFinancier;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Le gérant valide l'écart d'un versement (comme une clôture de caisse) : le compté entre au guichet, la caisse
 * du collecteur est soldée, le manquant devient une créance sur le collecteur, l'excédent un produit.
 */
final class ValiderVersement
{
    public function __construct(
        private readonly MoteurFinancier $moteur,
        private readonly OuvrirCompte $ouvrirCompte,
        private readonly CaisseCollecteur $caisses,
    ) {}

    public function executer(User $gerant, VersementCollecte $versement, string $motif): VersementCollecte
    {
        $motif = trim($motif);
        if ($motif === '' || mb_strlen($motif) > 500) {
            throw new InvalidArgumentException('Motif obligatoire (500 caractères au plus).');
        }

        return DB::transaction(function () use ($gerant, $versement, $motif) {
            $versement = VersementCollecte::query()->with('collecteur')->whereKey($versement->id)->lockForUpdate()->firstOrFail();
            AutoriserDansOrganisation::autoriser($gerant, 'valider', $versement);
            $devise = $versement->devise;
            $collecteur = $versement->collecteur;
            $guichet = SessionCaisse::query()->with('caisse')->findOrFail($versement->getAttribute('session_caisse_id'))->caisse->compte($devise);
            $source = $this->caisses->de($collecteur)?->compte($devise) ?? throw new InvalidArgumentException('Caisse du collecteur introuvable.');

            $lignes = [];
            if ($versement->compte->estPositif()) {
                $lignes[] = LigneEcriture::debit($guichet, $versement->compte);
            }
            if ($versement->theorique->estPositif()) {
                $lignes[] = LigneEcriture::credit($source, $versement->theorique);
            }
            if ($versement->ecart->estNegatif()) {
                $creance = $this->ouvrirCompte->obtenir("CREANCE-COLLECTEUR-{$collecteur->id}-{$devise->value}",
                    "Créance sur {$collecteur->name} (manquants de collecte)", NatureCompte::CreancePersonnel, $devise);
                $lignes[] = LigneEcriture::debit($creance, $versement->ecart->negation());
            } else {
                $produit = $this->ouvrirCompte->obtenir("PRODUIT-EXCEDENTS-CAISSE-{$devise->value}",
                    "Excédents de caisse ({$devise->value})", NatureCompte::Produit, $devise);
                $lignes[] = LigneEcriture::credit($produit, $versement->ecart);
            }

            $operation = $this->moteur->executer("versement-collecte:{$versement->id}", 'versement_collecte', $lignes,
                motif: $motif, validateur: $gerant);
            $versement->forceFill(['statut' => 'valide', 'operation_id' => $operation->id, 'valide_par_id' => $gerant->id, 'motif_validation' => $motif])->save();

            return $versement;
        });
    }
}
