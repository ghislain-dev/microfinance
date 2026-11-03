<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Collecte\Models\Appareil;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes de collecte : jeton délivré pour un téléphone toujours autorisé par le gérant (ADR 022).
 */
class AppareilAutorise
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();
        $jeton = $utilisateur instanceof User ? $utilisateur->currentAccessToken() : null;
        $autorise = $utilisateur instanceof User && $jeton instanceof PersonalAccessToken && $jeton->getAttribute('appareil_id') !== null
            && Appareil::query()->whereKey($jeton->getAttribute('appareil_id'))->where('user_id', $utilisateur->id)->where('statut', 'autorise')->exists();

        if (! $autorise) {
            return response()->json(['message' => 'Téléphone non autorisé.', 'code' => 'appareil_non_autorise'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
