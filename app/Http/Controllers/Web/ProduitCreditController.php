<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Credit\Actions\EnregistrerProduitCredit;
use App\Domain\Credit\Actions\FixerSeuilCredit;
use App\Domain\Credit\Models\ProduitCredit;
use App\Domain\Credit\Models\SeuilCredit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Credit\ProduitCreditRequest;
use App\Models\User;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantSaisi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class ProduitCreditController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', ProduitCredit::class);

        return view('credit.produits', [
            'produits' => ProduitCredit::query()->orderBy('code')->paginate(50),
            'seuils' => SeuilCredit::query()->get()->keyBy(fn (SeuilCredit $s) => $s->devise->value),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ProduitCredit::class);

        return view('credit.produit', ['produit' => null]);
    }

    public function store(ProduitCreditRequest $request, EnregistrerProduitCredit $enregistrer): RedirectResponse
    {
        Gate::authorize('create', ProduitCredit::class);

        try {
            $enregistrer->creer($this->acteur($request->user()), $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['montant_maximum' => $e->getMessage()]);
        }

        return to_route('credit.produits.index')->with('statut', 'Produit créé.');
    }

    public function edit(ProduitCredit $produit): View
    {
        Gate::authorize('update', $produit);

        return view('credit.produit', ['produit' => $produit]);
    }

    public function update(ProduitCreditRequest $request, ProduitCredit $produit, EnregistrerProduitCredit $enregistrer): RedirectResponse
    {
        Gate::authorize('update', $produit);

        try {
            $enregistrer->modifier($this->acteur($request->user()), $produit, [...$request->validated(), 'actif' => $request->boolean('actif')]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['montant_maximum' => $e->getMessage()]);
        }

        return to_route('credit.produits.index')->with('statut', 'Produit modifié.');
    }

    public function seuil(Request $request, FixerSeuilCredit $fixer): RedirectResponse
    {
        Gate::authorize('create', ProduitCredit::class);
        $donnees = $request->validate(['devise' => ['required', Rule::enum(Devise::class)], 'montant' => ['required', new MontantSaisi]]);
        $devise = Devise::from($donnees['devise']);
        $fixer->executer($this->acteur($request->user()), $devise, Montant::depuisSaisie($donnees['montant'], $devise));

        return to_route('credit.produits.index')->with('statut', "Seuil d'approbation du gérant enregistré ({$devise->value}).");
    }

    private function acteur(mixed $utilisateur): User
    {
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
