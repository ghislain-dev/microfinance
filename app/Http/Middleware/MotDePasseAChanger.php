<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tant que le mot de passe provisoire n'est pas changé, l'API ne sert que le changement de mot de passe.
 */
class MotDePasseAChanger
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();
        if ($utilisateur instanceof User && $utilisateur->getAttribute('mot_de_passe_provisoire')) {
            return response()->json([
                'message' => 'Changez votre mot de passe provisoire avant de continuer.',
                'code' => 'mot_de_passe_a_changer',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
