<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Domain\Organisation\Services\Authentificateur;
use App\Domain\Organisation\Services\ServiceOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConnexionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConnexionController extends Controller
{
    /** Clé de session : utilisateur dont le mot de passe est validé, en attente du code. */
    public const EN_ATTENTE = 'connexion.en_attente';

    public function create(): View
    {
        return view('auth.connexion');
    }

    public function store(ConnexionRequest $request, Authentificateur $authentificateur, ServiceOtp $otp): RedirectResponse
    {
        $utilisateur = $authentificateur->verifier($request->string('email')->value(), $request->string('password')->value(), accesWeb: true);

        if ($utilisateur === null) {
            return back()->withErrors(['email' => 'Identifiants incorrects.'])->onlyInput('email');
        }

        $otp->emettre($utilisateur);
        $request->session()->put(self::EN_ATTENTE, $utilisateur->id);

        return to_route('connexion.code');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }
}
