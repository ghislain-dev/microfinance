<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class MotDePasseController extends Controller
{
    /**
     * Changement de son mot de passe (obligatoire après un mot de passe provisoire).
     */
    public function update(Request $request): Response
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, Response::HTTP_UNAUTHORIZED);
        $donnees = $request->validate([
            'actuel' => ['required', 'string'],
            'nouveau' => ['required', 'string', 'confirmed', 'max:200', Password::min(8)->letters()->numbers()],
        ], ['nouveau.*' => 'Nouveau mot de passe : 8 caractères au moins, avec des lettres et des chiffres, saisi deux fois.']);

        if (! Hash::check($donnees['actuel'], $utilisateur->password)) {
            throw ValidationException::withMessages(['actuel' => 'Mot de passe actuel incorrect.']);
        }
        if (Hash::check($donnees['nouveau'], $utilisateur->password)) {
            throw ValidationException::withMessages(['nouveau' => 'Choisissez un mot de passe différent de l’actuel.']);
        }

        $utilisateur->forceFill(['password' => $donnees['nouveau'], 'mot_de_passe_provisoire' => false])->save();

        return response()->noContent();
    }
}
