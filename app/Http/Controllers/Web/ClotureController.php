<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Comptabilite\Actions\DeciderAffectation;
use App\Domain\Comptabilite\Actions\DeciderCloture;
use App\Domain\Comptabilite\Actions\DemanderCloture;
use App\Domain\Comptabilite\Actions\ProposerAffectation;
use App\Domain\Comptabilite\ClotureRefusee;
use App\Domain\Comptabilite\Models\AffectationResultat;
use App\Domain\Comptabilite\Models\DemandeCloture;
use App\Domain\Comptabilite\Services\ResultatAnnuel;
use App\Domain\Finance\Exceptions\RefusFinancier;
use App\Domain\Finance\Models\ClotureComptable;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantSaisi;
use App\Support\Temps\Horloge;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class ClotureController extends Controller
{
    public function index(Request $request, ResultatAnnuel $resultats): View
    {
        $acteur = $this->acteur($request);
        abort_unless(Gate::forUser($acteur)->any(['cloturer', 'validerClotures'], DemandeCloture::class), 403);
        $derniere = ClotureComptable::query()->max('date_fin');
        // Dernière année entièrement clôturée (31 décembre compris).
        $annee = $derniere === null ? null : (int) CarbonImmutable::parse((string) $derniere)->addDay()->subYear()->format('Y');

        return view('comptabilite.clotures', [
            'derniere' => $derniere,
            'annee' => $annee,
            'restants' => $annee === null ? [] : collect(Devise::cases())->mapWithKeys(fn (Devise $d) => [$d->value => $resultats->restantAAffecter($annee, $d)])->all(),
            'affectations' => AffectationResultat::query()->latest('id')->limit(20)->get(),
            'demandes' => DemandeCloture::query()->latest('id')->paginate(24),
            'utilisateurs' => User::query()->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, DemanderCloture $demander): RedirectResponse
    {
        Gate::authorize('cloturer', DemandeCloture::class);
        $mois = $request->validate(['mois' => ['required', 'date_format:Y-m']], ['mois.*' => 'Mois attendu au format AAAA-MM.'])['mois'];

        try {
            $demander->executer($this->acteur($request), CarbonImmutable::createFromFormat('!Y-m', $mois) ?: Horloge::aujourdhui());
        } catch (ClotureRefusee $e) {
            return back()->withInput()->withErrors(['mois' => $e->getMessage()]);
        }

        return to_route('comptabilite.clotures')->with('statut', 'Clôture demandée : le gérant doit la valider.');
    }

    public function decider(Request $request, DemandeCloture $demande, DeciderCloture $decider, string $decision): RedirectResponse
    {
        Gate::authorize('validerCloture', $demande);
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];

        try {
            $decision === 'valider' ? $decider->valider($this->acteur($request), $demande, $motif) : $decider->rejeter($this->acteur($request), $demande, $motif);
        } catch (ClotureRefusee|InvalidArgumentException $e) {
            return back()->withErrors(['motif' => $e->getMessage()]);
        }

        return to_route('comptabilite.clotures')->with('statut', $decision === 'valider' ? 'Mois clôturé définitivement.' : 'Demande rejetée.');
    }

    public function affecter(Request $request, ProposerAffectation $proposer): RedirectResponse
    {
        Gate::authorize('affecter', AffectationResultat::class);
        $donnees = $request->validate([
            'annee' => ['required', 'integer', 'min:2000', 'max:2100'],
            'devise' => ['required', Rule::enum(Devise::class)],
            'montant' => ['required', new MontantSaisi],
        ]);
        $devise = Devise::from($donnees['devise']);

        try {
            $proposer->executer($this->acteur($request), (int) $donnees['annee'], Montant::depuisSaisie($donnees['montant'], $devise));
        } catch (ClotureRefusee $e) {
            return back()->withInput()->withErrors(['montant' => $e->getMessage()]);
        }

        return to_route('comptabilite.clotures')->with('statut', 'Affectation proposée : le gérant doit la valider.');
    }

    public function deciderAffectation(Request $request, AffectationResultat $affectation, DeciderAffectation $decider, string $decision): RedirectResponse
    {
        Gate::authorize('validerAffectation', $affectation);
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];

        try {
            $decision === 'valider' ? $decider->valider($this->acteur($request), $affectation, $motif) : $decider->rejeter($this->acteur($request), $affectation, $motif);
        } catch (InvalidArgumentException|RefusFinancier $e) {
            return back()->withErrors(['motif' => $e->getMessage()]);
        }

        return to_route('comptabilite.clotures')->with('statut', $decision === 'valider' ? 'Résultat affecté aux excédents à répartir.' : 'Affectation rejetée.');
    }

    private function acteur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
