<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Finance\Exceptions\RefusFinancier;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Actions\CalculerPartage;
use App\Domain\Tontines\Actions\DeciderAide;
use App\Domain\Tontines\Actions\DeciderPartage;
use App\Domain\Tontines\Actions\DemanderAide;
use App\Domain\Tontines\Actions\DemarrerCycle;
use App\Domain\Tontines\Models\Aide;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Partage;
use App\Domain\Tontines\Models\Seance;
use App\Domain\Tontines\Models\Tour;
use App\Domain\Tontines\Services\Dettes;
use App\Domain\Tontines\TontineRefusee;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantSaisi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CycleController extends Controller
{
    public function show(Cycle $cycle, Dettes $dettes, MoteurFinancier $moteur): View
    {
        Gate::authorize('view', $cycle);
        $cycle->load(['groupe', 'compteFonds', 'compteFondsSocial']);
        $participants = $cycle->participants()->orderBy('numero')->get();
        $partage = Partage::query()->where('cycle_id', $cycle->id)->where('statut', '<>', 'rejete')->latest('id')->first();

        return view('tontines.cycle', [
            'cycle' => $cycle,
            'participants' => $participants,
            'membresDuGroupe' => $cycle->groupe->membres()->orderBy('numero')->get(),
            'dettes' => $participants->mapWithKeys(fn (Membre $m) => [$m->id => $dettes->du($cycle, $m)]),
            'seances' => Seance::query()->where('cycle_id', $cycle->id)->orderBy('rang')->get(),
            'tours' => Tour::query()->where('cycle_id', $cycle->id)->orderBy('rang')->get()->keyBy('rang'),
            'aides' => Aide::query()->with('membre:id,numero,nom')->where('cycle_id', $cycle->id)->latest('id')->get(),
            'partage' => $partage,
            'lignesPartage' => $partage?->lignes()->with('membre:id,numero,nom,postnom,prenom')->orderBy('membre_id')->get() ?? collect(),
            'soldeFonds' => $cycle->compteFonds === null ? null : $moteur->soldeReel($cycle->compteFonds),
            'soldeFondsSocial' => $cycle->compteFondsSocial === null ? null : $moteur->soldeReel($cycle->compteFondsSocial),
        ]);
    }

    public function demarrer(Request $request, Cycle $cycle, DemarrerCycle $demarrer): RedirectResponse
    {
        Gate::authorize('update', $cycle);
        $ordre = $request->input('ordre');
        $ordre = is_array($ordre) ? array_values(array_map('intval', $ordre)) : null;

        try {
            $demarrer->executer($this->acteur($request), $cycle, $ordre);
        } catch (TontineRefusee $e) {
            return back()->withErrors(['demarrage' => $e->getMessage()]);
        }

        return to_route('cycles.show', $cycle)->with('statut', 'Cycle démarré : séances planifiées.');
    }

    public function demanderAide(Request $request, Cycle $cycle, DemanderAide $demander): RedirectResponse
    {
        Gate::authorize('update', $cycle);
        $donnees = $request->validate(['membre_id' => ['required', 'integer'], 'montant' => ['required', new MontantSaisi], 'motif' => ['required', 'string', 'max:500']]);
        $membre = Membre::query()->findOrFail($donnees['membre_id']);

        try {
            $demander->executer($this->acteur($request), $cycle, $membre, Montant::depuisSaisie($donnees['montant'], $cycle->devise), $donnees['motif']);
        } catch (TontineRefusee $e) {
            return back()->withInput()->withErrors(['aide' => $e->getMessage()]);
        }

        return to_route('cycles.show', $cycle)->with('statut', 'Aide transmise au comité.');
    }

    public function deciderAide(Request $request, Aide $aide, DeciderAide $decider, string $decision): RedirectResponse
    {
        Gate::authorize('decider', $aide);
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];

        try {
            $decision === 'valider' ? $decider->valider($this->acteur($request), $aide, $motif) : $decider->rejeter($this->acteur($request), $aide, $motif);
        } catch (TontineRefusee|RefusFinancier $e) {
            return back()->withErrors(['aide' => $e->getMessage()]);
        }

        return to_route('cycles.show', $aide->cycle_id)->with('statut', $decision === 'valider' ? 'Aide versée.' : 'Aide rejetée.');
    }

    public function calculerPartage(Request $request, Cycle $cycle, CalculerPartage $calculer): RedirectResponse
    {
        Gate::authorize('update', $cycle);

        try {
            $calculer->executer($this->acteur($request), $cycle);
        } catch (TontineRefusee $e) {
            return back()->withErrors(['partage' => $e->getMessage()]);
        }

        return to_route('cycles.show', $cycle)->with('statut', 'Partage calculé : le comité doit le valider.');
    }

    public function deciderPartage(Request $request, Partage $partage, DeciderPartage $decider, string $decision): RedirectResponse
    {
        Gate::authorize('decider', $partage);
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];

        try {
            $decision === 'valider' ? $decider->valider($this->acteur($request), $partage, $motif) : $decider->rejeter($this->acteur($request), $partage, $motif);
        } catch (TontineRefusee|RefusFinancier $e) {
            return back()->withErrors(['partage' => $e->getMessage()]);
        }

        return to_route('cycles.show', $partage->cycle_id)->with('statut', $decision === 'valider' ? 'Partage versé, cycle clôturé.' : 'Partage rejeté.');
    }

    private function acteur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
