<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Collecte\Actions\AffecterMembre;
use App\Domain\Collecte\Actions\DeciderAppareil;
use App\Domain\Collecte\Actions\FixerParametresCollecte;
use App\Domain\Collecte\Actions\RetirerMembre;
use App\Domain\Collecte\CollecteRefusee;
use App\Domain\Collecte\Models\Appareil;
use App\Domain\Collecte\Models\ParametresCollecte;
use App\Domain\Collecte\Models\PlafondCollecte;
use App\Domain\Collecte\Models\Tournee;
use App\Domain\Membres\Models\Membre;
use App\Domain\Organisation\Acces\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Montant\Devise;
use App\Support\Montant\MontantSaisi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CollecteController extends Controller
{
    public function index(): View
    {
        Gate::authorize('gerer', Tournee::class);
        $collecteurs = User::role(Role::Collecteur->value)->orderBy('name')->get();

        return view('collecte.index', [
            'collecteurs' => $collecteurs,
            'appareils' => Appareil::query()->whereIn('user_id', $collecteurs->pluck('id'))->latest('id')->get()->groupBy('user_id'),
            'tournees' => Tournee::query()->with('membre:id,numero,nom,postnom,prenom')->whereIn('collecteur_id', $collecteurs->pluck('id'))
                ->whereNull('retire_le')->get()->groupBy('collecteur_id'),
            'parametres' => ParametresCollecte::query()->first(),
            'plafonds' => PlafondCollecte::query()->get()->keyBy(fn (PlafondCollecte $p) => $p->devise->value),
        ]);
    }

    public function appareil(Request $request, Appareil $appareil, DeciderAppareil $decider, string $decision): RedirectResponse
    {
        Gate::authorize('decider', $appareil);
        $decision === 'autoriser' ? $decider->autoriser($this->acteur($request), $appareil) : $decider->revoquer($this->acteur($request), $appareil);

        return to_route('collecte.index')->with('statut', $decision === 'autoriser' ? "Téléphone {$appareil->modele} autorisé." : "Téléphone {$appareil->modele} révoqué.");
    }

    public function affecter(Request $request, User $collecteur, AffecterMembre $affecter): RedirectResponse
    {
        Gate::authorize('gerer', Tournee::class);
        $numero = $request->validate(['numero' => ['required', 'string', 'max:40']])['numero'];
        $membre = Membre::query()->where('numero', trim($numero))->first();
        if ($membre === null) {
            return back()->withInput()->withErrors(['numero' => "Aucun membre n° {$numero}."]);
        }

        try {
            $affecter->executer($this->acteur($request), $collecteur, $membre);
        } catch (CollecteRefusee $e) {
            return back()->withErrors(['numero' => $e->getMessage()]);
        }

        return to_route('collecte.index')->with('statut', "{$membre->numero} ajouté à la tournée de {$collecteur->name}.");
    }

    public function retirer(Request $request, User $collecteur, Membre $membre, RetirerMembre $retirer): RedirectResponse
    {
        Gate::authorize('gerer', Tournee::class);
        $retirer->executer($this->acteur($request), $collecteur, $membre);

        return to_route('collecte.index')->with('statut', "{$membre->numero} retiré de la tournée de {$collecteur->name}.");
    }

    public function parametres(Request $request, FixerParametresCollecte $fixer): RedirectResponse
    {
        Gate::authorize('gerer', Tournee::class);
        $regles = ['delai_max_heures' => ['required', 'integer', 'min:1', 'max:720']];
        foreach (Devise::cases() as $devise) {
            $regles["plafonds.{$devise->value}.plafond_retrait"] = ['required', new MontantSaisi(zeroAutorise: true)];
            $regles["plafonds.{$devise->value}.plafond_especes"] = ['required', new MontantSaisi(zeroAutorise: true)];
        }
        $donnees = $request->validate($regles);

        try {
            $fixer->executer($this->acteur($request), (int) $donnees['delai_max_heures'], $donnees['plafonds']);
        } catch (CollecteRefusee $e) {
            return back()->withInput()->withErrors(['plafonds' => $e->getMessage()]);
        }

        return to_route('collecte.index')->with('statut', 'Paramètres de collecte enregistrés.');
    }

    private function acteur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
