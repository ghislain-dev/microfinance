<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Organisation\Services\Authentificateur;
use App\Domain\Organisation\Services\ResultatOtp;
use App\Domain\Organisation\Services\ServiceOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\JetonRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class JetonController extends Controller
{
    /**
     * 1er appel sans code : vérifie le mot de passe et envoie un code (202).
     * 2e appel avec le code : délivre un jeton pour l'appareil (201).
     */
    public function store(JetonRequest $request, Authentificateur $authentificateur, ServiceOtp $otp): JsonResponse
    {
        $utilisateur = $authentificateur->verifier($request->string('email')->value(), $request->string('password')->value(), accesWeb: false)
            ?? throw ValidationException::withMessages(['email' => 'Identifiants incorrects.']);

        if (! $request->filled('code')) {
            $otp->emettre($utilisateur);

            return response()->json(['statut' => 'code_requis'], Response::HTTP_ACCEPTED);
        }

        if ($otp->verifier($utilisateur, $request->string('code')->value()) !== ResultatOtp::Valide) {
            throw ValidationException::withMessages(['code' => 'Code incorrect ou expiré.']);
        }

        return $this->delivrer($utilisateur, $request->string('appareil')->value());
    }

    public function destroy(Request $request): Response
    {
        $jeton = $request->user()?->currentAccessToken();
        if ($jeton instanceof PersonalAccessToken) {
            $jeton->delete();
        }

        return response()->noContent();
    }

    private function delivrer(User $utilisateur, string $appareil): JsonResponse
    {
        $jeton = $utilisateur->createToken($appareil, ['*'], now()->addMinutes((int) config('sanctum.expiration')));

        return response()->json([
            'jeton' => $jeton->plainTextToken,
            'expire_le' => $jeton->accessToken->expires_at?->toIso8601String(),
        ], Response::HTTP_CREATED);
    }
}
