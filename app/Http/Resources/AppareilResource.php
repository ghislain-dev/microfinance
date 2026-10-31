<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Un jeton Sanctum = un appareil connecté.
 *
 * @mixin PersonalAccessToken
 */
class AppareilResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->name,
            'cree_le' => $this->created_at?->toIso8601String(),
            'derniere_utilisation' => $this->last_used_at?->toIso8601String(),
            'expire_le' => $this->expires_at?->toIso8601String(),
            'courant' => $request->user()?->currentAccessToken()?->getKey() === $this->id,
        ];
    }
}
