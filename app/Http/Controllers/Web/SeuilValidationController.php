<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Epargne\Actions\FixerSeuil;
use App\Domain\Epargne\Models\SeuilValidation;
use App\Domain\Epargne\Models\TypeCompteEpargne;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantSaisi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SeuilValidationController extends Controller
{
    public function index(): View
    {
        Gate::authorize('create', TypeCompteEpargne::class); // paramétrage de l'épargne

        return view('epargne.seuils', ['seuils' => SeuilValidation::query()->get()->keyBy(fn (SeuilValidation $s) => $s->devise->value)]);
    }

    public function store(Request $request, FixerSeuil $fixer): RedirectResponse
    {
        Gate::authorize('create', TypeCompteEpargne::class);
        $donnees = $request->validate(['devise' => ['required', Rule::enum(Devise::class)], 'montant' => ['required', new MontantSaisi]]);
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        $devise = Devise::from($donnees['devise']);
        $fixer->executer($utilisateur, $devise, Montant::depuisSaisie($donnees['montant'], $devise));

        return to_route('epargne.seuils')->with('statut', "Seuil {$devise->value} enregistré.");
    }
}
