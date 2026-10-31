<?php

declare(strict_types=1);

namespace App\Domain\Change\Actions;

use App\Domain\Change\Models\Conversion;
use App\Domain\Change\Models\TauxChange;
use App\Domain\Change\Taux;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Services\RegleEpargne;
use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Support\Facades\DB;

/**
 * Conversion USD/CDF entre deux comptes d'un même membre (CLAUDE.md §4.5) : deux opérations liées,
 * une par devise, au taux du jour. USD → CDF au taux d'achat, CDF → USD au taux de vente ; montant
 * arrondi au centime inférieur ; la marge par rapport au taux moyen va en produit.
 */
final class Convertir
{
    public function __construct(
        private readonly MoteurFinancier $moteur,
        private readonly RegleEpargne $regles,
        private readonly OuvrirCompte $ouvrirCompte,
    ) {}

    public function executer(User $operateur, CompteEpargne $source, CompteEpargne $destination, Montant $montant, string $cle): Conversion
    {
        $source->loadMissing(['membre', 'type', 'compte']);
        $destination->loadMissing(['membre', 'type', 'compte']);
        AutoriserDansOrganisation::autoriser($operateur, 'virer', [CompteEpargne::class, $source, $destination]);

        if (($deja = $this->moteur->dejaEnregistree("{$cle}:source")) !== null) {
            return Conversion::query()->where('operation_source_id', $deja->id)->firstOrFail();
        }
        if ($source->membre_id !== $destination->membre_id) {
            throw new CompteEpargneRefuse('Le change se fait entre deux comptes du même membre.');
        }
        if ($source->type->devise === $destination->type->devise) {
            throw new CompteEpargneRefuse('Les deux comptes sont dans la même devise : utilisez un virement.');
        }
        $this->regles->exigerDevise($source, $montant);
        $this->regles->exigerMembreActif($source, 'change');
        $this->regles->exigerNonBloque($source);
        $taux = TauxChange::duJour() ?? throw new CompteEpargneRefuse('Aucun taux de change saisi aujourd’hui : demandez au gérant ou au comptable.');

        [$tauxApplique, $recu, $contreValeur] = $this->calculer($montant, $taux);
        $marge = $contreValeur->moins($recu);

        return DB::transaction(function () use ($source, $destination, $montant, $cle, $taux, $tauxApplique, $recu, $contreValeur, $marge) {
            Compte::query()->whereKey($source->compte_id)->lockForUpdate()->firstOrFail();
            $this->regles->exigerSoldeMinimum($this->moteur->soldeReel($source->compte)->moins($montant), $source);

            $positionSource = $this->position($montant->devise);
            $operationSource = $this->moteur->executer("{$cle}:source", 'change', [
                LigneEcriture::debit($source->compte, $montant),
                LigneEcriture::credit($positionSource, $montant),
            ]);

            $lignes = [
                LigneEcriture::debit($this->position($recu->devise), $contreValeur),
                LigneEcriture::credit($destination->compte, $recu),
            ];
            if ($marge->estPositif()) {
                $lignes[] = LigneEcriture::credit($this->ouvrirCompte->obtenir("PRODUIT-CHANGE-{$marge->devise->value}",
                    "Marge de change ({$marge->devise->value})", NatureCompte::Produit, $marge->devise), $marge);
            }
            $operationDestination = $this->moteur->executer("{$cle}:destination", 'change', $lignes);

            $conversion = new Conversion;
            $conversion->forceFill([
                'organisation_id' => $source->organisation_id,
                'membre_id' => $source->membre_id,
                'compte_source_id' => $source->id,
                'compte_destination_id' => $destination->id,
                'taux_change_id' => $taux->id,
                'taux_applique' => $tauxApplique->millioniemes,
                'devise_source' => $montant->devise,
                'montant_source' => $montant,
                'devise_destination' => $recu->devise,
                'montant_destination' => $recu,
                'marge' => $marge,
                'operation_source_id' => $operationSource->id,
                'operation_destination_id' => $operationDestination->id,
            ])->save();

            $this->regles->enregistrerMouvement($operationSource->id, $source, 'change_sortant', $montant);
            $this->regles->enregistrerMouvement($operationDestination->id, $destination, 'change_entrant', $recu);

            return $conversion;
        });
    }

    /**
     * @return array{Taux, Montant, Montant} taux appliqué, montant reçu par le membre, contre-valeur au taux moyen
     */
    private function calculer(Montant $montant, TauxChange $taux): array
    {
        $moyen = Taux::moyenEntre($taux->achat(), $taux->vente());

        return $montant->devise === Devise::USD
            ? [$taux->achat(), $taux->achat()->versCdf($montant), $moyen->versCdf($montant)]
            : [$taux->vente(), $taux->vente()->versUsd($montant), $moyen->versUsd($montant)];
    }

    private function position(Devise $devise): Compte
    {
        return $this->ouvrirCompte->obtenir("POSITION-CHANGE-{$devise->value}", "Position de change ({$devise->value})",
            NatureCompte::PositionChange, $devise);
    }
}
