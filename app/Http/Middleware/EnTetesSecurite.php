<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité de references/securite.md, sur toutes les réponses (web et API).
 */
class EnTetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        $reponse->headers->set('Content-Security-Policy', $this->politique());
        $reponse->headers->set('X-Frame-Options', 'DENY');
        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->headers->set('Referrer-Policy', 'same-origin');
        $reponse->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (app()->isProduction()) {
            $reponse->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $reponse;
    }

    private function politique(): string
    {
        $scripts = $styles = $connexions = "'self'";

        // Poste de développement : serveur Vite (npm run dev) et pages d'erreur détaillées.
        if (app()->isLocal()) {
            $vite = Vite::isRunningHot() ? trim((string) file_get_contents(Vite::hotFile())) : '';
            $scripts .= " 'unsafe-inline' {$vite}";
            $styles .= " 'unsafe-inline' {$vite}";
            $connexions .= ' '.str_replace(['http://', 'https://'], ['ws://', 'wss://'], $vite)." {$vite}";
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src {$scripts}",
            "style-src {$styles}",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src {$connexions}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
