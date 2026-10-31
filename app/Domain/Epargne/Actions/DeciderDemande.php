<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Actions;

use App\Domain\Epargne\Models\DemandeDecaissement;
use App\Domain\Epargne\StatutDemande;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Décision du gérant ou du comptable sur un gros décaissement. Un virement approuvé est exécuté aussitôt ;
 * un retrait approuvé attend le paiement au guichet.
 */
final class DeciderDemande
{
    public function __construct(private readonly Virer $virer) {}

    public function approuver(User $validateur, DemandeDecaissement $demande, string $motif): DemandeDecaissement
    {
        return $this->decider($validateur, $demande, $motif, StatutDemande::Approuvee);
    }

    public function rejeter(User $validateur, DemandeDecaissement $demande, string $motif): DemandeDecaissement
    {
        return $this->decider($validateur, $demande, $motif, StatutDemande::Rejetee);
    }

    private function decider(User $validateur, DemandeDecaissement $demande, string $motif, StatutDemande $decision): DemandeDecaissement
    {
        $motif = trim($motif);
        if ($motif === '' || mb_strlen($motif) > 500) {
            throw new InvalidArgumentException('Motif obligatoire (500 caractères au plus).');
        }
        AutoriserDansOrganisation::autoriser($validateur, 'decider', $demande);

        return DB::transaction(function () use ($validateur, $demande, $motif, $decision) {
            $demande->forceFill([
                'statut' => $decision, 'decide_par_id' => $validateur->id, 'decide_le' => now(), 'motif_decision' => $motif,
            ])->save();
            $demande->setRelation('decidePar', $validateur);

            if ($decision === StatutDemande::Approuvee && $demande->type === 'virement') {
                $this->virer->executerDemande($demande);
            }

            return $demande;
        });
    }
}
