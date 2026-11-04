<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Comptabilite\Actions\DeciderCloture;
use App\Domain\Comptabilite\Actions\DemanderCloture;
use App\Domain\Comptabilite\ClotureRefusee;
use App\Domain\Comptabilite\Models\DemandeCloture;
use App\Domain\Finance\Models\ClotureComptable;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class ClotureController extends Controller
{
    public function index(Request $request): View
    {
        $acteur = $this->acteur($request);
        abort_unless(Gate::forUser($acteur)->any(['cloturer', 'validerClotures'], DemandeCloture::class), 403);

        return view('comptabilite.clotures', [
            'derniere' => ClotureComptable::query()->max('date_fin'),
            'demandes' => DemandeCloture::query()->latest('id')->paginate(24),
            'utilisateurs' => User::query()->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, DemanderCloture $demander): RedirectResponse
    {
        Gate::authorize('cloturer', DemandeCloture::class);
        $mois = $request->validate(['mois' => ['required', 'date_format:Y-m']], ['mois.*' => 'Mois attendu au format AAAA-MM.'])['mois'];

        try {
            $demander->executer($this->acteur($request), CarbonImmutable::createFromFormat('!Y-m', $mois) ?: CarbonImmutable::today());
        } catch (ClotureRefusee $e) {
            return back()->withInput()->withErrors(['mois' => $e->getMessage()]);
        }

        return to_route('comptabilite.clotures')->with('statut', 'Clôture demandée : le gérant doit la valider.');
    }

    public function decider(Request $request, DemandeCloture $demande, DeciderCloture $decider, string $decision): RedirectResponse
    {
        Gate::authorize('validerCloture', $demande);
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];

        try {
            $decision === 'valider' ? $decider->valider($this->acteur($request), $demande, $motif) : $decider->rejeter($this->acteur($request), $demande, $motif);
        } catch (ClotureRefusee|InvalidArgumentException $e) {
            return back()->withErrors(['motif' => $e->getMessage()]);
        }

        return to_route('comptabilite.clotures')->with('statut', $decision === 'valider' ? 'Mois clôturé définitivement.' : 'Demande rejetée.');
    }

    private function acteur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
