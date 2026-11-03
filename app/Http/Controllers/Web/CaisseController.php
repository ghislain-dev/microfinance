<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Caisse\Actions\CreerCaisse;
use App\Domain\Caisse\Models\Caisse;
use App\Domain\Caisse\Models\SessionCaisse;
use App\Domain\Caisse\StatutSession;
use App\Domain\Organisation\Models\Agence;
use App\Http\Controllers\Controller;
use App\Http\Requests\Caisse\CaisseRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CaisseController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Caisse::class);

        return view('caisses.index', [
            'caisses' => Caisse::query()->with(['agence:id,code,nom', 'sessionEnCours.caissier:id,name'])
                ->whereNull('collecteur_id')->orderBy('code')->paginate(50),
            'aValider' => SessionCaisse::query()->with(['caisse:id,code,libelle', 'caissier:id,name'])
                ->whereIn('statut', [StatutSession::OuvertureAValider->value, StatutSession::EcartAJustifier->value])
                ->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Caisse::class);

        return view('caisses.creer', ['agences' => Agence::query()->where('actif', true)->orderBy('nom')->get()]);
    }

    public function store(CaisseRequest $request, CreerCaisse $creer): RedirectResponse
    {
        Gate::authorize('create', Caisse::class);
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        $creer->executer($utilisateur, Agence::query()->findOrFail($request->integer('agence_id')),
            $request->string('code')->value(), $request->string('libelle')->value());

        return to_route('caisses.index')->with('statut', 'Caisse créée.');
    }
}
