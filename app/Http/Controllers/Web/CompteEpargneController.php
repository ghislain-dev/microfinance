<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Epargne\Actions\OuvrirCompteEpargne;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\TypeCompteEpargne;
use App\Domain\Membres\Models\Membre;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CompteEpargneController extends Controller
{
    public function store(Request $request, Membre $membre, OuvrirCompteEpargne $ouvrir): RedirectResponse
    {
        Gate::authorize('create', [CompteEpargne::class, $membre]);
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        $donnees = $request->validate([
            'type_compte_epargne_id' => ['required', 'integer', Rule::exists('types_comptes_epargne', 'id')
                ->where('organisation_id', $utilisateur->organisation_id)->where('actif', true)],
        ], ['type_compte_epargne_id.*' => 'Choisissez un produit d’épargne proposé.']);

        try {
            $compte = $ouvrir->executer($utilisateur, $membre, TypeCompteEpargne::query()->findOrFail($donnees['type_compte_epargne_id']));
        } catch (CompteEpargneRefuse $e) {
            return back()->withErrors(['type_compte_epargne_id' => $e->getMessage()]);
        }

        return to_route('membres.show', $membre)->with('statut', "Compte {$compte->numero} ouvert.");
    }
}
