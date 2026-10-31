<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Epargne\Actions\DeciderDemande;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Models\DemandeDecaissement;
use App\Domain\Epargne\StatutDemande;
use App\Domain\Finance\Exceptions\RefusFinancier;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DemandeDecaissementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', DemandeDecaissement::class);

        return view('epargne.demandes', [
            'enAttente' => DemandeDecaissement::query()->with(['compteEpargne:id,numero', 'destination:id,numero', 'demandeur:id,name'])
                ->where('statut', StatutDemande::EnAttente->value)->orderBy('id')->get(),
            'recentes' => DemandeDecaissement::query()->with(['compteEpargne:id,numero', 'decidePar:id,name'])
                ->where('statut', '<>', StatutDemande::EnAttente->value)->latest('id')->paginate(25),
        ]);
    }

    public function approuver(Request $request, DemandeDecaissement $demande, DeciderDemande $decider): RedirectResponse
    {
        return $this->decider($request, $demande, fn (User $u, string $motif) => $decider->approuver($u, $demande, $motif), 'Demande validée.');
    }

    public function rejeter(Request $request, DemandeDecaissement $demande, DeciderDemande $decider): RedirectResponse
    {
        return $this->decider($request, $demande, fn (User $u, string $motif) => $decider->rejeter($u, $demande, $motif), 'Demande rejetée.');
    }

    /**
     * @param  callable(User, string): DemandeDecaissement  $decision
     */
    private function decider(Request $request, DemandeDecaissement $demande, callable $decision, string $succes): RedirectResponse
    {
        Gate::authorize('decider', $demande);
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']], ['motif.required' => 'Le motif est obligatoire.']);
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        try {
            $decision($utilisateur, $donnees['motif']);
        } catch (CompteEpargneRefuse|RefusFinancier $e) {
            return back()->withErrors(['motif' => $e->getMessage()]);
        }

        return to_route('demandes.index')->with('statut', $succes);
    }
}
