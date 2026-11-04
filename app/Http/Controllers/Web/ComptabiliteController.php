<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Comptabilite\Actions\ModifierCorrespondance;
use App\Domain\Comptabilite\Models\CorrespondanceSyscohada;
use App\Domain\Comptabilite\PlanSyscohada;
use App\Domain\Comptabilite\Services\EtatsComptables;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Montant\Devise;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * États comptables par devise (ADR 023) : consultation par le comptable et le gérant.
 */
class ComptabiliteController extends Controller
{
    public function balance(Request $request, EtatsComptables $etats): View
    {
        [$devise, $du, $au] = $this->periode($request);

        return view('comptabilite.balance', ['balance' => $etats->balance($devise, $du, $au), 'du' => $du, 'au' => $au]);
    }

    public function grandLivre(Request $request, EtatsComptables $etats): View
    {
        [$devise, $du, $au] = $this->periode($request);
        $rubrique = $request->validate(['rubrique' => ['required', Rule::in(array_keys(PlanSyscohada::PROPOSITION))]])['rubrique'];

        return view('comptabilite.grand-livre', ['livre' => $etats->grandLivre($rubrique, $devise, $du, $au), 'devise' => $devise, 'du' => $du, 'au' => $au]);
    }

    public function journal(Request $request, EtatsComptables $etats): View
    {
        [$devise, $du, $au] = $this->periode($request);

        return view('comptabilite.journal', ['operations' => $etats->journal($devise, $du, $au)->withQueryString(), 'devise' => $devise, 'du' => $du, 'au' => $au]);
    }

    public function plan(PlanSyscohada $plan): View
    {
        Gate::authorize('voir', CorrespondanceSyscohada::class);

        return view('comptabilite.plan', ['correspondances' => $plan->toutes()]);
    }

    public function modifierPlan(Request $request, ModifierCorrespondance $modifier): RedirectResponse
    {
        Gate::authorize('parametrer', CorrespondanceSyscohada::class);
        $donnees = $request->validate([
            'rubrique' => ['required', Rule::in(array_keys(PlanSyscohada::PROPOSITION))],
            'numero' => ['required', 'string', 'regex:/^[1-7]\d{1,7}$/'],
            'libelle' => ['required', 'string', 'max:150'],
        ], ['numero.regex' => 'Numéro SYSCOHADA : 2 à 8 chiffres, classe 1 à 7.']);

        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);
        try {
            $modifier->executer($utilisateur, $donnees['rubrique'], $donnees['numero'], $donnees['libelle']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['numero' => $e->getMessage()]);
        }

        return to_route('comptabilite.plan')->with('statut', 'Correspondance enregistrée.');
    }

    /**
     * @return array{Devise, CarbonImmutable, CarbonImmutable}
     */
    private function periode(Request $request): array
    {
        Gate::authorize('voir', CorrespondanceSyscohada::class);
        $donnees = $request->validate([
            'devise' => ['nullable', Rule::enum(Devise::class)],
            'du' => ['nullable', 'date_format:Y-m-d'],
            'au' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:du'],
        ]);
        $du = isset($donnees['du']) ? CarbonImmutable::parse($donnees['du']) : CarbonImmutable::today()->startOfMonth();

        return [
            Devise::from($donnees['devise'] ?? Devise::USD->value),
            $du,
            isset($donnees['au']) ? CarbonImmutable::parse($donnees['au']) : $du->endOfMonth()->startOfDay(),
        ];
    }
}
