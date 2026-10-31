<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Actions;

use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Exceptions\ValidationRequise;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\DemandeDecaissement;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\Services\RegleEpargne;
use App\Domain\Epargne\StatutDemande;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Montant;
use Illuminate\Support\Facades\DB;

/**
 * Virement entre comptes d'épargne de l'organisation, même devise, sans frais, sans espèces.
 */
final class Virer
{
    public function __construct(private readonly MoteurFinancier $moteur, private readonly RegleEpargne $regles) {}

    public function executer(User $operateur, CompteEpargne $source, CompteEpargne $destination, Montant $montant, string $cle): MouvementEpargne
    {
        AutoriserDansOrganisation::autoriser($operateur, 'virer', [$source, $destination]);
        $this->controler($source, $destination, $montant);

        if (($deja = $this->moteur->dejaEnregistree($cle)) !== null) {
            return $this->regles->enregistrerMouvement($deja->id, $source, 'virement_sortant', $montant);
        }
        if ($this->regles->depasseSeuil($montant)) {
            throw new ValidationRequise($this->regles->creerDemande($operateur, 'virement', $source, $montant, $destination));
        }

        return $this->virer($source, $destination, $montant, $cle);
    }

    /**
     * Exécution d'une demande de virement approuvée (appelée par DeciderDemande).
     */
    public function executerDemande(DemandeDecaissement $demande): MouvementEpargne
    {
        $source = $demande->compteEpargne;
        $destination = $demande->destination ?? throw new CompteEpargneRefuse('Demande de virement sans destination.');
        $this->regles->exigerDemandeApprouvee($demande, 'virement', $source, $demande->montant);
        $this->controler($source, $destination, $demande->montant);

        return DB::transaction(function () use ($demande, $source, $destination) {
            $mouvement = $this->virer($source, $destination, $demande->montant, "demande:{$demande->id}", $demande);
            $demande->forceFill(['statut' => StatutDemande::Executee, 'operation_id' => $mouvement->operation_id])->save();

            return $mouvement;
        });
    }

    private function controler(CompteEpargne $source, CompteEpargne $destination, Montant $montant): void
    {
        $source->loadMissing(['membre', 'type', 'compte']);
        $destination->loadMissing(['membre', 'type', 'compte']);
        if ($source->id === $destination->id) {
            throw new CompteEpargneRefuse('Le compte de destination doit être différent du compte source.');
        }
        if ($source->type->devise !== $destination->type->devise) {
            throw new CompteEpargneRefuse('Virement impossible entre deux devises : utilisez le change.');
        }
        $this->regles->exigerDevise($source, $montant);
        $this->regles->exigerMembreActif($source, 'virement');
        $this->regles->exigerMembreActif($destination, 'virement entrant');
        $this->regles->exigerNonBloque($source);
    }

    private function virer(CompteEpargne $source, CompteEpargne $destination, Montant $montant, string $cle, ?DemandeDecaissement $demande = null): MouvementEpargne
    {
        return DB::transaction(function () use ($source, $destination, $montant, $cle, $demande) {
            Compte::query()->whereKey($source->compte_id)->lockForUpdate()->firstOrFail();
            $this->regles->exigerSoldeMinimum($this->moteur->soldeReel($source->compte)->moins($montant), $source);

            $operation = $this->moteur->executer($cle, 'virement', [
                LigneEcriture::debit($source->compte, $montant),
                LigneEcriture::credit($destination->compte, $montant),
            ], validateur: $demande?->decidePar);

            $this->regles->enregistrerMouvement($operation->id, $destination, 'virement_entrant', $montant);

            return $this->regles->enregistrerMouvement($operation->id, $source, 'virement_sortant', $montant);
        });
    }
}
