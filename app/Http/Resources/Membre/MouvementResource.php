<?php

declare(strict_types=1);

namespace App\Http\Resources\Membre;

use App\Domain\Epargne\Models\MouvementEpargne;
use App\Http\Resources\MontantJson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MouvementEpargne
 */
class MouvementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'libelle' => $this->libelleType(),
            'montant' => MontantJson::de($this->montant),
            'frais' => MontantJson::de($this->frais),
            'recu' => $this->recu?->getAttribute('numero'),
            'le' => $this->created_at->toIso8601String(),
        ];
    }
}
