<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Epargne\Models\TypeCompteEpargne;
use App\Domain\Epargne\Services\ComptesDuMembre;
use App\Domain\Membres\Actions\ChangerStatutMembre;
use App\Domain\Membres\Actions\CreerAccesMobile;
use App\Domain\Membres\Actions\InscrireMembre;
use App\Domain\Membres\Actions\ModifierMembre;
use App\Domain\Membres\Exceptions\PieceDejaEnregistree;
use App\Domain\Membres\Exceptions\TelephoneDejaUtilise;
use App\Domain\Membres\Models\Membre;
use App\Domain\Membres\StatutMembre;
use App\Domain\Organisation\Models\Agence;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membres\FicheMembreRequest;
use App\Http\Requests\Membres\InscriptionMembreRequest;
use App\Models\User;
use App\Support\Telephone;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MembreController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Membre::class);
        $recherche = trim($request->string('q')->value());

        $membres = Membre::query()
            ->with('agence:id,code,nom')
            ->when($recherche !== '', function ($requete) use ($recherche) {
                $telephone = Telephone::normaliser($recherche);
                $requete->where(fn ($q) => $q
                    ->where('numero', $recherche)
                    ->orWhere('nom', 'like', $recherche.'%')
                    ->orWhere('postnom', 'like', $recherche.'%')
                    ->orWhere('prenom', 'like', $recherche.'%')
                    ->when($telephone !== null, fn ($q) => $q->orWhere('telephone', $telephone)));
            })
            ->orderBy('agence_id')->orderBy('numero_sequence')
            ->paginate(25)
            ->withQueryString();

        return view('membres.index', ['membres' => $membres, 'recherche' => $recherche]);
    }

    public function create(): View
    {
        Gate::authorize('create', Membre::class);

        return view('membres.creer', ['agences' => Agence::query()->where('actif', true)->orderBy('nom')->get()]);
    }

    public function store(InscriptionMembreRequest $request, InscrireMembre $inscrire): RedirectResponse
    {
        Gate::authorize('create', Membre::class);
        $agence = Agence::query()->findOrFail($request->integer('agence_id'));

        return $this->avecControleDoublons(fn () => $inscrire->executer(
            $this->acteur($request), $agence, $request->validated(), $request->telephonePartageConfirme(),
        ));
    }

    public function show(Membre $membre, ComptesDuMembre $comptes): View
    {
        Gate::authorize('view', $membre);

        return view('membres.fiche', [
            'membre' => $membre->load('agence'),
            'statuts' => StatutMembre::cases(),
            'comptes' => $comptes->pour($membre),
            'typesProposes' => TypeCompteEpargne::query()->where('actif', true)->orderBy('code')->get(),
            'accesMobile' => User::query()->where('membre_id', $membre->id)->exists(),
        ]);
    }

    public function edit(Membre $membre): View
    {
        Gate::authorize('update', $membre);

        return view('membres.modifier', ['membre' => $membre]);
    }

    public function update(FicheMembreRequest $request, Membre $membre, ModifierMembre $modifier): RedirectResponse
    {
        Gate::authorize('update', $membre);

        return $this->avecControleDoublons(fn () => $modifier->executer(
            $this->acteur($request), $membre, $request->validated(), $request->telephonePartageConfirme(),
        ));
    }

    public function changerStatut(Request $request, Membre $membre, ChangerStatutMembre $changer): RedirectResponse
    {
        Gate::authorize('changerStatut', $membre);
        $donnees = $request->validate(['statut' => ['required', Rule::enum(StatutMembre::class)]]);

        $changer->executer($this->acteur($request), $membre, StatutMembre::from($donnees['statut']));

        return to_route('membres.show', $membre)->with('statut', 'Statut mis à jour.');
    }

    /**
     * Crée ou réinitialise l'accès mobile ; le mot de passe provisoire n'est montré qu'une fois.
     */
    public function accesMobile(Request $request, Membre $membre, CreerAccesMobile $creer): RedirectResponse
    {
        Gate::authorize('accesMobile', $membre);
        [, $provisoire] = $creer->executer($this->acteur($request), $membre);

        return to_route('membres.show', $membre)->with('acces_mobile', [
            'organisation' => $membre->organisation?->code, 'numero' => $membre->numero, 'mot_de_passe' => $provisoire,
        ]);
    }

    /**
     * @param  Closure(): Membre  $enregistrer
     */
    private function avecControleDoublons(Closure $enregistrer): RedirectResponse
    {
        try {
            $membre = $enregistrer();
        } catch (PieceDejaEnregistree $e) {
            return back()->withInput()->withErrors(['piece_numero' => $e->getMessage()]);
        } catch (TelephoneDejaUtilise $e) {
            return back()->withInput()
                ->withErrors(['telephone' => $e->getMessage().' Cochez la case pour confirmer un téléphone partagé.'])
                ->with('telephone_partage', true);
        }

        return to_route('membres.show', $membre)->with('statut', 'Fiche enregistrée.');
    }

    private function acteur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
