<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Epargne\Actions\EnregistrerTypeCompte;
use App\Domain\Epargne\Models\TypeCompteEpargne;
use App\Http\Controllers\Controller;
use App\Http\Requests\Epargne\TypeCompteRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TypeCompteEpargneController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', TypeCompteEpargne::class);

        return view('epargne.types', ['types' => TypeCompteEpargne::query()->orderBy('code')->paginate(50)]);
    }

    public function create(): View
    {
        Gate::authorize('create', TypeCompteEpargne::class);

        return view('epargne.type', ['type' => null]);
    }

    public function store(TypeCompteRequest $request, EnregistrerTypeCompte $enregistrer): RedirectResponse
    {
        Gate::authorize('create', TypeCompteEpargne::class);
        $enregistrer->creer($this->acteur($request->user()), $request->validated());

        return to_route('epargne.types.index')->with('statut', 'Produit créé.');
    }

    public function edit(TypeCompteEpargne $type): View
    {
        Gate::authorize('update', $type);

        return view('epargne.type', ['type' => $type]);
    }

    public function update(TypeCompteRequest $request, TypeCompteEpargne $type, EnregistrerTypeCompte $enregistrer): RedirectResponse
    {
        Gate::authorize('update', $type);
        $enregistrer->modifier($this->acteur($request->user()), $type, [...$request->validated(), 'actif' => $request->boolean('actif')]);

        return to_route('epargne.types.index')->with('statut', 'Produit modifié.');
    }

    private function acteur(mixed $utilisateur): User
    {
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
