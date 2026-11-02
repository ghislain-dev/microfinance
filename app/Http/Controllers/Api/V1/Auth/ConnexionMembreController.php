<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Membres\Models\Membre;
use App\Domain\Organisation\Models\Organisation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Connexion d'un membre à l'application mobile (ADR 022) : code de l'organisation + numéro de membre
 * + mot de passe. Jeton à capacité « membre » seulement.
 */
class ConnexionMembreController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'organisation' => ['required', 'string', 'max:30'],
            'numero' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string', 'max:200'],
            'appareil' => ['required', 'string', 'max:100'],
        ]);

        $organisation = Organisation::query()->where('code', $donnees['organisation'])->where('actif', true)->first();
        $membre = $organisation === null ? null
            : Membre::withoutGlobalScopes()->where('organisation_id', $organisation->id)->where('numero', $donnees['numero'])->first();
        $utilisateur = $membre === null ? null
            : User::withoutGlobalScopes()->where('organisation_id', $membre->organisation_id)->where('membre_id', $membre->id)->first();

        // Hachage calculé même si le membre est inconnu : même temps de réponse, même message.
        $bon = Hash::check($donnees['password'], $utilisateur->password ?? Hash::make('compte-inexistant'));
        if ($utilisateur === null || ! $bon || ! $utilisateur->actif) {
            event(new Failed('sanctum', $utilisateur, ['numero' => $donnees['numero']]));

            throw ValidationException::withMessages(['numero' => 'Identifiants incorrects.']);
        }

        $jeton = $utilisateur->createToken($donnees['appareil'], ['membre'], now()->addMinutes((int) config('sanctum.expiration')));

        return response()->json([
            'jeton' => $jeton->plainTextToken,
            'expire_le' => $jeton->accessToken->expires_at?->toIso8601String(),
            'doit_changer_mot_de_passe' => (bool) $utilisateur->getAttribute('mot_de_passe_provisoire'),
        ], Response::HTTP_CREATED);
    }
}
