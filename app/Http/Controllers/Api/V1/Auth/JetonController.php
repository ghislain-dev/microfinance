<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Collecte\Models\Appareil;
use App\Domain\Organisation\Acces\Droit;
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

        // Collecteur : seulement depuis un téléphone autorisé par le gérant (ADR 022), avant tout envoi de code.
        $appareil = null;
        if ($this->estCollecteur($utilisateur)) {
            $appareil = $this->appareil($utilisateur, $request);
            if ($appareil->statut !== 'autorise') {
                return response()->json([
                    'message' => $appareil->statut === 'revoque' ? 'Ce téléphone a été révoqué.' : 'Téléphone en attente d’autorisation par le gérant.',
                    'code' => $appareil->statut === 'revoque' ? 'appareil_revoque' : 'appareil_en_attente',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        if (! $request->filled('code')) {
            $otp->emettre($utilisateur);

            return response()->json(['statut' => 'code_requis'], Response::HTTP_ACCEPTED);
        }

        if ($otp->verifier($utilisateur, $request->string('code')->value()) !== ResultatOtp::Valide) {
            throw ValidationException::withMessages(['code' => 'Code incorrect ou expiré.']);
        }

        return $this->delivrer($utilisateur, $request->string('appareil')->value(), $appareil);
    }

    public function destroy(Request $request): Response
    {
        $jeton = $request->user()?->currentAccessToken();
        if ($jeton instanceof PersonalAccessToken) {
            $jeton->delete();
        }

        return response()->noContent();
    }

    private function estCollecteur(User $utilisateur): bool
    {
        setPermissionsTeamId($utilisateur->organisation_id);

        return $utilisateur->can(Droit::CollecteSaisir->value);
    }

    private function appareil(User $collecteur, JetonRequest $request): Appareil
    {
        if (! $request->filled('appareil_id')) {
            throw ValidationException::withMessages(['appareil_id' => 'Identifiant du téléphone obligatoire pour un collecteur.']);
        }
        $identifiant = $request->string('appareil_id')->value();

        $appareil = Appareil::withoutGlobalScopes()->where('user_id', $collecteur->id)->where('identifiant', $identifiant)->first();
        if ($appareil === null) {
            $appareil = new Appareil;
            $appareil->forceFill([
                'organisation_id' => $collecteur->organisation_id, 'user_id' => $collecteur->id, 'identifiant' => $identifiant,
                'modele' => $request->string('appareil')->value(), 'statut' => 'en_attente',
            ])->save();
        }

        return $appareil;
    }

    private function delivrer(User $utilisateur, string $appareil, ?Appareil $telephone): JsonResponse
    {
        // Capacités : « collecteur » (collecte et synchronisation) ou « personnel ».
        $jeton = $utilisateur->createToken($appareil, [$telephone !== null ? 'collecteur' : 'personnel'], now()->addMinutes((int) config('sanctum.expiration')));
        if ($telephone !== null) {
            $jeton->accessToken->forceFill(['appareil_id' => $telephone->id])->save();
        }

        return response()->json([
            'jeton' => $jeton->plainTextToken,
            'expire_le' => $jeton->accessToken->expires_at?->toIso8601String(),
        ], Response::HTTP_CREATED);
    }
}
