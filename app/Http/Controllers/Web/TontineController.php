<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Membres\Models\Membre;
use App\Domain\Organisation\Models\Agence;
use App\Domain\Tontines\Actions\CreerCycle;
use App\Domain\Tontines\Actions\CreerGroupe;
use App\Domain\Tontines\Actions\InscrireAuGroupe;
use App\Domain\Tontines\Models\Groupe;
use App\Domain\Tontines\TontineRefusee;
use App\Domain\Tontines\TypeGroupe;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tontines\CycleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class TontineController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Groupe::class);

        return view('tontines.index', [
            'groupes' => Groupe::query()->with('agence:id,code')->withCount('membres')->orderBy('code')->paginate(50),
            'agences' => Agence::query()->where('actif', true)->orderBy('nom')->get(),
        ]);
    }

    public function store(Request $request, CreerGroupe $creer): RedirectResponse
    {
        Gate::authorize('create', Groupe::class);
        $organisation = $this->acteur($request)->organisation_id;
        $donnees = $request->validate([
            'agence_id' => ['required', 'integer', Rule::exists('agences', 'id')->where('organisation_id', $organisation)],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('groupes_tontine', 'code')->where('organisation_id', $organisation)],
            'nom' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(TypeGroupe::class)],
        ], ['code.regex' => 'Code : majuscules, chiffres et tirets.', 'code.unique' => 'Ce code existe déjà.']);

        $groupe = $creer->executer($this->acteur($request), Agence::query()->findOrFail($donnees['agence_id']),
            $donnees['code'], $donnees['nom'], TypeGroupe::from($donnees['type']));

        return to_route('tontines.show', $groupe)->with('statut', 'Groupe créé.');
    }

    public function show(Groupe $groupe): View
    {
        Gate::authorize('view', $groupe);

        return view('tontines.groupe', [
            'groupe' => $groupe->load('agence'),
            'membres' => $groupe->membres()->orderBy('numero')->get(),
            'cycles' => $groupe->cycles()->orderByDesc('numero')->get(),
        ]);
    }

    public function inscrire(Request $request, Groupe $groupe, InscrireAuGroupe $inscrire): RedirectResponse
    {
        Gate::authorize('update', $groupe);
        $numero = trim($request->validate(['numero' => ['required', 'string', 'max:40']])['numero']);
        $membre = Membre::query()->where('numero', $numero)->first();
        if ($membre === null) {
            return back()->withInput()->withErrors(['numero' => 'Aucun membre avec ce numéro.']);
        }

        try {
            $inscrire->executer($this->acteur($request), $groupe, $membre);
        } catch (TontineRefusee $e) {
            return back()->withInput()->withErrors(['numero' => $e->getMessage()]);
        }

        return to_route('tontines.show', $groupe)->with('statut', "{$membre->nomComplet()} inscrit(e).");
    }

    public function creerCycle(CycleRequest $request, Groupe $groupe, CreerCycle $creer): RedirectResponse
    {
        Gate::authorize('update', $groupe);

        try {
            $cycle = $creer->executer($this->acteur($request), $groupe, $request->parametres());
        } catch (InvalidArgumentException $e) {
            $champ = str_contains($e->getMessage(), 'séances') ? 'nombre_seances' : (str_contains($e->getMessage(), 'tours') ? 'mode_tours' : 'montant_cotisation');

            return back()->withInput()->withErrors([$champ => $e->getMessage()]);
        }

        return to_route('cycles.show', $cycle)->with('statut', 'Cycle préparé : vérifiez les paramètres puis démarrez-le.');
    }

    private function acteur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
