<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Caisse\Actions\CloturerSession;
use App\Domain\Caisse\Actions\OuvrirSession;
use App\Domain\Caisse\Actions\ValiderEcart;
use App\Domain\Caisse\Billetage;
use App\Domain\Caisse\Exceptions\SessionDejaOuverte;
use App\Domain\Caisse\Exceptions\SessionInvalide;
use App\Domain\Caisse\Models\Caisse;
use App\Domain\Caisse\Models\LigneBilletage;
use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Caisse\MomentComptage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Caisse\BilletageRequest;
use App\Models\User;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SessionCaisseController extends Controller
{
    public function create(Caisse $caisse): View
    {
        Gate::authorize('operer', $caisse);

        return view('caisses.ouvrir', ['caisse' => $caisse]);
    }

    public function store(BilletageRequest $request, Caisse $caisse, OuvrirSession $ouvrir): RedirectResponse
    {
        Gate::authorize('operer', $caisse);

        try {
            $session = $ouvrir->executer($this->acteur($request), $caisse, $request->billetage());
        } catch (SessionDejaOuverte $e) {
            return back()->withInput()->withErrors(['billetage' => $e->getMessage()]);
        }

        return to_route('sessions-caisse.show', $session);
    }

    public function show(SessionCaisse $sessionCaisse): View
    {
        Gate::authorize('view', $sessionCaisse);
        $sessionCaisse->load(['caisse.agence', 'caissier:id,name', 'ecarts']);

        return view('caisses.session', [
            'session' => $sessionCaisse,
            'totaux' => $this->totauxComptes($sessionCaisse),
        ]);
    }

    public function cloturer(BilletageRequest $request, SessionCaisse $sessionCaisse, CloturerSession $cloturer): RedirectResponse
    {
        Gate::authorize('cloturer', $sessionCaisse);

        try {
            $cloturer->executer($this->acteur($request), $sessionCaisse, $request->billetage());
        } catch (SessionInvalide $e) {
            return back()->withErrors(['billetage' => $e->getMessage()]);
        }

        return to_route('sessions-caisse.show', $sessionCaisse);
    }

    public function valider(Request $request, SessionCaisse $sessionCaisse, ValiderEcart $valider): RedirectResponse
    {
        Gate::authorize('validerEcart', $sessionCaisse);
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']], ['motif.required' => 'Le motif est obligatoire.']);

        try {
            $valider->executer($this->acteur($request), $sessionCaisse, $donnees['motif']);
        } catch (SessionInvalide $e) {
            return back()->withErrors(['motif' => $e->getMessage()]);
        }

        return to_route('sessions-caisse.show', $sessionCaisse)->with('statut', 'Écart validé et comptabilisé.');
    }

    /**
     * Totaux des comptages, calculés en entiers côté serveur (jamais en JavaScript).
     *
     * @return array<string, array<string, Montant>> moment => devise => total
     */
    private function totauxComptes(SessionCaisse $session): array
    {
        $totaux = [];
        foreach (MomentComptage::cases() as $moment) {
            $saisie = [];
            foreach (LigneBilletage::query()->where('session_caisse_id', $session->id)->where('moment', $moment->value)->get() as $ligne) {
                $saisie[(string) $ligne->getAttribute('devise')][(string) $ligne->getAttribute('coupure')] = (int) $ligne->getAttribute('nombre');
            }
            $billetage = Billetage::depuisSaisie($saisie);
            foreach (Devise::cases() as $devise) {
                $totaux[$moment->value][$devise->value] = $billetage->total($devise);
            }
        }

        return $totaux;
    }

    private function acteur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
