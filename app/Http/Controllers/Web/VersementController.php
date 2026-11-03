<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Caisse\Exceptions\SessionInvalide;
use App\Domain\Collecte\Actions\EnregistrerVersement;
use App\Domain\Collecte\Actions\ValiderVersement;
use App\Domain\Collecte\CollecteRefusee;
use App\Domain\Collecte\Models\VersementCollecte;
use App\Domain\Collecte\Services\CaisseCollecteur;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Finance\Exceptions\RefusFinancier;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Organisation\Acces\Droit;
use App\Domain\Organisation\Acces\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collecte\VersementRequest;
use App\Models\User;
use App\Support\Montant\Devise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Versements des collecteurs au guichet (caissier) et validation des écarts (gérant).
 */
class VersementController extends Controller
{
    public function index(Request $request, CaisseCollecteur $caisses, MoteurFinancier $moteur): View
    {
        $acteur = $this->acteur($request);
        abort_unless($acteur->canAny([Droit::CaisseOperer->value, Droit::CaisseValiderEcart->value]), 403);
        $collecteurs = User::role(Role::Collecteur->value)->orderBy('name')->get();

        return view('collecte.versements', [
            'especes' => $collecteurs->mapWithKeys(function (User $c) use ($caisses, $moteur) {
                $caisse = $caisses->de($c);

                return [$c->id => $caisse === null ? [] : collect(Devise::cases())
                    ->mapWithKeys(fn (Devise $d) => [$d->value => $moteur->soldeReel($caisse->compte($d))])->all()];
            }),
            'collecteurs' => $collecteurs,
            'versements' => VersementCollecte::query()->with('collecteur:id,name')->latest('id')->paginate(30),
        ]);
    }

    public function store(VersementRequest $request, EnregistrerVersement $enregistrer): RedirectResponse
    {
        Gate::authorize('recevoir', VersementCollecte::class);
        $collecteur = User::query()->findOrFail($request->integer('collecteur_id'));

        try {
            $versements = $enregistrer->executer($this->acteur($request), $collecteur, $request->billetage());
        } catch (CollecteRefusee|CompteEpargneRefuse|RefusFinancier|SessionInvalide $e) {
            return back()->withInput()->withErrors(['billetage' => $e->getMessage()]);
        }
        $ecart = collect($versements)->contains(fn (VersementCollecte $v) => $v->statut === 'ecart_a_justifier');

        return to_route('versements.index')->with('statut', $ecart
            ? 'Versement enregistré avec un écart : le gérant doit le valider.'
            : 'Versement enregistré : les espèces sont dans votre caisse.');
    }

    public function valider(Request $request, VersementCollecte $versement, ValiderVersement $valider): RedirectResponse
    {
        Gate::authorize('valider', $versement);
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];

        try {
            $valider->executer($this->acteur($request), $versement, $motif);
        } catch (InvalidArgumentException|RefusFinancier $e) {
            return back()->withErrors(['motif' => $e->getMessage()]);
        }

        return to_route('versements.index')->with('statut', 'Écart de versement validé.');
    }

    private function acteur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
