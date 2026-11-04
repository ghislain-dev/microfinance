<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Organisation\Models\Organisation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OrganisationController extends Controller
{
    public function show(Request $request): View
    {
        $organisation = $this->organisation($request);
        Gate::authorize('update', $organisation);

        return view('organisation.parametres', ['organisation' => $organisation]);
    }

    /**
     * Logo des reçus : PNG ou JPEG, 512 Ko au plus, stocké hors du dossier public sous un nom aléatoire.
     */
    public function logo(Request $request): RedirectResponse
    {
        $organisation = $this->organisation($request);
        Gate::authorize('update', $organisation);
        $request->validate(['logo' => ['required', 'file', 'mimes:png,jpg,jpeg', 'mimetypes:image/png,image/jpeg',
            'max:512', 'dimensions:max_width=2000,max_height=2000']], [
                'logo.*' => 'Logo : image PNG ou JPEG de 512 Ko au plus (2000 × 2000 pixels maximum).',
            ]);

        $ancien = $organisation->logo_chemin;
        $chemin = $request->file('logo')?->store("organisations/{$organisation->id}", 'local');
        $organisation->forceFill(['logo_chemin' => $chemin])->save(); // journalisé (EstAudite)
        if ($ancien !== null) {
            Storage::disk('local')->delete($ancien);
        }

        return to_route('organisation.show')->with('statut', 'Logo enregistré : il apparaîtra sur les prochains reçus.');
    }

    /**
     * Rappel push des échéances de prêt : nombre de jours avant (vide = aucun rappel), ADR 022.
     */
    public function rappels(Request $request): RedirectResponse
    {
        $organisation = $this->organisation($request);
        Gate::authorize('update', $organisation);
        $jours = $request->validate(['rappel_echeance_jours' => ['nullable', 'integer', 'min:1', 'max:30']],
            ['rappel_echeance_jours.*' => 'Rappel : de 1 à 30 jours avant l’échéance, ou vide pour aucun rappel.'])['rappel_echeance_jours'] ?? null;

        $organisation->forceFill(['rappel_echeance_jours' => $jours === null ? null : (int) $jours])->save();

        return to_route('organisation.show')->with('statut', $jours === null ? 'Rappels d’échéance désactivés.' : "Rappel envoyé {$jours} jour(s) avant chaque échéance.");
    }

    private function organisation(Request $request): Organisation
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return Organisation::query()->findOrFail($utilisateur->organisation_id);
    }
}
