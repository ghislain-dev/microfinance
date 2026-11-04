<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Epargne\Remuneration\Actions\CalculerInterets;
use App\Domain\Epargne\Remuneration\Actions\CalculerRistourne;
use App\Domain\Epargne\Remuneration\Actions\DeciderRemuneration;
use App\Domain\Epargne\Remuneration\Models\Remuneration;
use App\Domain\Epargne\Remuneration\RemunerationRefusee;
use App\Domain\Finance\Exceptions\RefusFinancier;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Organisation\Models\Organisation;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantSaisi;
use App\Support\Temps\Horloge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class RemunerationController extends Controller
{
    public function index(MoteurFinancier $moteur): View
    {
        Gate::authorize('viewAny', Remuneration::class);

        $excedents = [];
        foreach (Devise::cases() as $devise) {
            $compte = Compte::query()->where('numero', "EXCEDENTS-A-REPARTIR-{$devise->value}")->first();
            $excedents[$devise->value] = $compte === null ? Montant::zero($devise) : $moteur->soldeReel($compte);
        }

        return view('remunerations.index', [
            'lots' => Remuneration::query()->with(['creePar:id,name', 'decidePar:id,name'])->latest('id')->paginate(25),
            'excedents' => $excedents,
            'moisEcoule' => Horloge::aujourdhui()->subMonthNoOverflow()->format('Y-m'),
        ]);
    }

    public function show(Remuneration $lot): View
    {
        Gate::authorize('viewAny', Remuneration::class);

        return view('remunerations.lot', [
            'lot' => $lot->load(['creePar:id,name', 'decidePar:id,name']),
            'lignes' => $lot->lignes()->with('compteEpargne:id,numero')->orderBy('id')->paginate(50),
        ]);
    }

    public function interets(Request $request, CalculerInterets $calculer): RedirectResponse
    {
        Gate::authorize('create', Remuneration::class);
        $donnees = $request->validate([
            'periode' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'devise' => ['required', Rule::enum(Devise::class)],
        ], ['periode.*' => 'Mois au format AAAA-MM.']);

        try {
            $lot = $calculer->executer(Organisation::query()->findOrFail($this->acteur($request)->organisation_id),
                $donnees['periode'], Devise::from($donnees['devise']));
        } catch (InvalidArgumentException|RemunerationRefusee $e) {
            return back()->withInput()->withErrors(['periode' => $e->getMessage()]);
        }

        return to_route('epargne.remunerations.show', $lot)->with('statut', 'Intérêts calculés : relisez puis faites valider.');
    }

    public function ristourne(Request $request, CalculerRistourne $calculer): RedirectResponse
    {
        Gate::authorize('create', Remuneration::class);
        $donnees = $request->validate([
            'montant' => ['required', new MontantSaisi],
            'devise' => ['required', Rule::enum(Devise::class)],
            'date_reference' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], ['date_reference.*' => 'Date de référence passée, au format AAAA-MM-JJ.']);

        try {
            $lot = $calculer->executer($this->acteur($request),
                Montant::depuisSaisie($donnees['montant'], Devise::from($donnees['devise'])), Carbon::parse($donnees['date_reference']));
        } catch (InvalidArgumentException|RemunerationRefusee $e) {
            return back()->withInput()->withErrors(['montant' => $e->getMessage()]);
        }

        return to_route('epargne.remunerations.show', $lot)->with('statut', 'Ristourne répartie : une autre personne doit la valider.');
    }

    public function valider(Request $request, Remuneration $lot, DeciderRemuneration $decider): RedirectResponse
    {
        return $this->decider($request, $lot, fn (User $u, string $motif) => $decider->valider($u, $lot, $motif), 'Lot validé : montants versés.');
    }

    public function rejeter(Request $request, Remuneration $lot, DeciderRemuneration $decider): RedirectResponse
    {
        return $this->decider($request, $lot, fn (User $u, string $motif) => $decider->rejeter($u, $lot, $motif), 'Lot rejeté.');
    }

    /**
     * @param  callable(User, string): Remuneration  $decision
     */
    private function decider(Request $request, Remuneration $lot, callable $decision, string $succes): RedirectResponse
    {
        Gate::authorize('decider', $lot);
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']], ['motif.required' => 'Le motif est obligatoire.']);

        try {
            $decision($this->acteur($request), $donnees['motif']);
        } catch (RefusFinancier $e) {
            return back()->withErrors(['motif' => 'Versement impossible : '.$e->getMessage()]);
        }

        return to_route('epargne.remunerations.show', $lot)->with('statut', $succes);
    }

    private function acteur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
