<?php

declare(strict_types=1);

namespace App\Http\Resources\Membre;

use App\Domain\Epargne\Models\CompteEpargne;
use App\Http\Resources\MontantJson;
use App\Support\Montant\Montant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compte d'épargne vu par son titulaire ; soldes calculés par le serveur (jamais par le téléphone).
 *
 * @property array{compte: CompteEpargne, solde: Montant, bloque: Montant, disponible: Montant} $resource
 */
class CompteMembreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $compte = $this->resource['compte'];

        return [
            'id' => $compte->id,
            'numero' => $compte->numero,
            'produit' => $compte->type->libelle,
            'devise' => $compte->type->devise->value,
            'solde' => MontantJson::de($this->resource['solde']),
            'bloque' => MontantJson::de($this->resource['bloque']),
            'disponible' => MontantJson::de($this->resource['disponible']),
            'bloque_jusqu_au' => $compte->bloque_jusqu_au?->toDateString(),
        ];
    }
}
