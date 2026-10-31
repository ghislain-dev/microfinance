<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Domain\Organisation\Services\Authentificateur;
use App\Domain\Organisation\Services\ResultatOtp;
use App\Domain\Organisation\Services\ServiceOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CodeOtpRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CodeOtpController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($this->enAttente($request) === null) {
            return to_route('login');
        }

        return view('auth.code');
    }

    public function store(CodeOtpRequest $request, ServiceOtp $otp, Authentificateur $authentificateur): RedirectResponse
    {
        $utilisateur = $this->enAttente($request);
        if ($utilisateur === null || ! $authentificateur->autorise($utilisateur, accesWeb: true)) {
            $request->session()->forget(ConnexionController::EN_ATTENTE);

            return to_route('login');
        }

        return match ($otp->verifier($utilisateur, $request->string('code')->value())) {
            ResultatOtp::Valide => $this->connecter($request, $utilisateur),
            ResultatOtp::Incorrect => back()->withErrors(['code' => 'Code incorrect.']),
            ResultatOtp::AucunCode => $this->recommencer($request),
        };
    }

    public function renvoyer(Request $request, ServiceOtp $otp): RedirectResponse
    {
        $utilisateur = $this->enAttente($request);
        if ($utilisateur === null) {
            return to_route('login');
        }

        if (! $otp->emettre($utilisateur)) {
            return back()->withErrors(['code' => 'Patientez une minute avant de demander un nouveau code.']);
        }

        return to_route('connexion.code')->with('statut', 'Un nouveau code vous a été envoyé.');
    }

    private function connecter(Request $request, User $utilisateur): RedirectResponse
    {
        $request->session()->forget(ConnexionController::EN_ATTENTE);
        Auth::guard('web')->login($utilisateur);
        $request->session()->regenerate();

        return redirect()->intended(route('tableau-de-bord'));
    }

    private function recommencer(Request $request): RedirectResponse
    {
        $request->session()->forget(ConnexionController::EN_ATTENTE);

        return to_route('login')->withErrors(['code' => 'Code expiré ou trop d’essais : reconnectez-vous.']);
    }

    private function enAttente(Request $request): ?User
    {
        $id = $request->session()->get(ConnexionController::EN_ATTENTE);

        return is_int($id) ? User::find($id) : null;
    }
}
