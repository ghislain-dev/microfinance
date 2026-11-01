<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Finance\Exceptions\RefusFinancier;
use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Actions\SaisirSeance;
use App\Domain\Tontines\Models\Cotisation;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\PresenceTontine;
use App\Domain\Tontines\Models\Seance;
use App\Domain\Tontines\Models\Tour;
use App\Domain\Tontines\Services\Dettes;
use App\Domain\Tontines\TontineRefusee;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use ValueError;

class SeanceController extends Controller
{
    public function show(Seance $seance, Dettes $dettes): View
    {
        Gate::authorize('view', $seance);
        $cycle = Cycle::query()->with('groupe')->findOrFail($seance->cycle_id);
        $participants = $cycle->participants()->orderBy('numero')->get();

        return view('tontines.seance', [
            'seance' => $seance,
            'cycle' => $cycle,
            'participants' => $participants,
            'dettes' => $participants->mapWithKeys(fn (Membre $m) => [$m->id => $dettes->du($cycle, $m)]),
            'tour' => Tour::query()->where('cycle_id', $cycle->id)->where('rang', $seance->rang)->first(),
            'sansTour' => $participants->whereNotIn('id', Tour::query()->where('cycle_id', $cycle->id)->pluck('membre_id')),
            'presences' => PresenceTontine::query()->where('seance_id', $seance->id)->pluck('presence', 'membre_id'),
            'encaissements' => Cotisation::query()->where('seance_id', $seance->id)->get()->groupBy('membre_id'),
        ]);
    }

    public function store(Request $request, Seance $seance, SaisirSeance $saisir): RedirectResponse
    {
        Gate::authorize('saisir', $seance);
        $donnees = $request->validate([
            'lignes' => ['required', 'array'],
            // Chaque champ doit avoir une règle : validate() ne renvoie que les champs validés.
            'lignes.*.presence' => ['required', 'string'],
            'lignes.*.cotisation' => ['nullable', 'string', 'max:20'],
            'lignes.*.parts' => ['nullable', 'integer'],
            'lignes.*.fonds_social' => ['nullable', 'string', 'max:20'],
            'lignes.*.reglement' => ['nullable', 'string', 'max:20'],
            'enchere.membre_id' => ['nullable', 'integer'],
            'enchere.remise' => ['nullable', 'string', 'max:20'],
        ]);
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);
        $enchere = isset($donnees['enchere']['membre_id'])
            ? ['membre_id' => (int) $donnees['enchere']['membre_id'], 'remise' => (string) ($donnees['enchere']['remise'] ?? '0')] : null;

        try {
            $saisir->executer($utilisateur, $seance, $donnees['lignes'], $enchere);
        } catch (TontineRefusee|CompteEpargneRefuse|RefusFinancier|ValueError $e) {
            return back()->withInput()->withErrors(['lignes' => $e instanceof ValueError ? 'Présence invalide.' : $e->getMessage()]);
        }

        return to_route('seances.show', $seance)->with('statut', 'Séance enregistrée.');
    }
}
