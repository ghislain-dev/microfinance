<?php

declare(strict_types=1);

namespace App\Domain\Rapports\Policies;

use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Organisation\Acces\Droit;
use App\Domain\Rapports\Models\Export;
use App\Models\User;

/**
 * Droits par rapport (ADR 023). Le membre ne demande que le relevé de ses propres comptes.
 */
class ExportPolicy
{
    /**
     * @param  array<string, mixed>  $parametres
     */
    public function demander(User $acteur, string $type, array $parametres = []): bool
    {
        if ($acteur->membreId() !== null) {
            return $type === 'releve' && CompteEpargne::query()->whereKey((int) ($parametres['compte_epargne_id'] ?? 0))
                ->where('membre_id', $acteur->membreId())->exists();
        }

        $droit = match ($type) {
            'balance', 'grand_livre', 'journal' => Droit::ComptaVoir,
            'releve' => Droit::RapportsReleve,
            'portefeuille' => Droit::RapportsCredit,
            'caisse' => Droit::RapportsCaisse,
            default => null,
        };

        return $droit !== null && $acteur->can($droit->value);
    }

    public function telecharger(User $acteur, Export $export): bool
    {
        return $export->user_id === $acteur->id && $export->statut === 'pret';
    }
}
