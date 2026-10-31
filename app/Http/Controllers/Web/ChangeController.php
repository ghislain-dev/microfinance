<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Change\Actions\SaisirTaux;
use App\Domain\Change\Models\TauxChange;
use App\Domain\Change\Taux;
use App\Http\Controllers\Controller;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class ChangeController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', TauxChange::class);
        $duJour = TauxChange::duJour();

        return view('change.index', [
            'duJour' => $duJour,
            'moyen' => $duJour === null ? null : Taux::moyenEntre($duJour->achat(), $duJour->vente()),
            'historique' => TauxChange::query()->with('saisiPar:id,name')->latest('id')->paginate(30),
        ]);
    }

    public function store(Request $request, SaisirTaux $saisir): RedirectResponse
    {
        Gate::authorize('create', TauxChange::class);
        $taux = fn (string $attribut, mixed $valeur, Closure $echec) => rescue(
            fn () => Taux::depuisSaisie((string) $valeur),
            fn () => $echec('Taux invalide : nombre positif, 6 décimales au plus (ex. 2850,50).'),
            false,
        );
        $donnees = $request->validate(['achat' => ['required', 'string', $taux], 'vente' => ['required', 'string', $taux]]);
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        try {
            $saisir->executer($utilisateur, Taux::depuisSaisie($donnees['achat']), Taux::depuisSaisie($donnees['vente']));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['achat' => $e->getMessage()]);
        }

        return to_route('change.index')->with('statut', 'Taux du jour enregistré.');
    }
}
