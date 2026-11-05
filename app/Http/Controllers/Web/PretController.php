<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Credit\Actions\AnalyserPret;
use App\Domain\Credit\Actions\DecaisserPret;
use App\Domain\Credit\Actions\DeciderPret;
use App\Domain\Credit\Actions\DeciderSaisieGarantie;
use App\Domain\Credit\Actions\DemanderPret;
use App\Domain\Credit\Actions\DemanderSaisieGarantie;
use App\Domain\Credit\Actions\RembourserPret;
use App\Domain\Credit\CreditRefuse;
use App\Domain\Credit\MethodeCalcul;
use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\Models\GarantiePret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\ProduitCredit;
use App\Domain\Credit\Models\Remboursement;
use App\Domain\Credit\Models\SaisieGarantie;
use App\Domain\Credit\ModeRemboursement;
use App\Domain\Credit\Services\IndicateursCredit;
use App\Domain\Credit\StatutPret;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Finance\Exceptions\RefusFinancier;
use App\Domain\Membres\Models\Membre;
use App\Http\Controllers\Controller;
use App\Http\Requests\Credit\DemandePretRequest;
use App\Models\User;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantSaisi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PretController extends Controller
{
    public function index(Request $request, IndicateursCredit $indicateurs): View
    {
        Gate::authorize('viewAny', Pret::class);
        $statut = $request->validate(['statut' => ['nullable', Rule::enum(StatutPret::class)]])['statut'] ?? null;

        return view('credit.index', [
            'prets' => Pret::query()->with('membre:id,numero,nom,postnom,prenom')
                ->when($statut !== null, fn ($q) => $q->where('statut', $statut))->latest('id')->paginate(30)->withQueryString(),
            'statut' => $statut,
            'par' => collect(Devise::cases())->map(fn (Devise $d) => [$indicateurs->par(30, $d), $indicateurs->par(90, $d)])
                ->filter(fn (array $p) => $p[0]->encours->estPositif())->values(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Pret::class);
        $numero = trim($request->string('membre')->value());
        $membre = $numero === '' ? null : Membre::query()->where('numero', $numero)->first();

        return view('credit.demande', [
            'numero' => $numero,
            'membre' => $membre,
            'comptes' => $membre === null ? collect() : CompteEpargne::query()->with('type:id,code,libelle,devise')->where('membre_id', $membre->id)->orderBy('id')->get(),
            'produits' => ProduitCredit::query()->where('actif', true)->orderBy('code')->get(),
        ]);
    }

    public function store(DemandePretRequest $request, DemanderPret $demander): RedirectResponse
    {
        $donnees = $request->validated();
        $membre = Membre::query()->findOrFail($donnees['membre_id']);
        $produit = ProduitCredit::query()->findOrFail($donnees['produit_credit_id']);
        Gate::authorize('create', [Pret::class, $membre, $produit]);
        $compte = CompteEpargne::query()->findOrFail($donnees['compte_epargne_id']);

        $pieces = [];
        try {
            $garanties = [];
            foreach ($donnees['garanties'] ?? [] as $i => $ligne) {
                if ($ligne['type'] === 'caution') {
                    if (blank($ligne['membre_numero'] ?? null) && blank($ligne['montant'] ?? null)) {
                        continue; // ligne laissée vide
                    }
                    $caution = Membre::query()->where('numero', trim((string) ($ligne['membre_numero'] ?? '')))->first();
                    if ($caution === null) {
                        return back()->withInput()->withErrors(['garanties' => "Caution n° {$ligne['membre_numero']} introuvable."]);
                    }
                    $garanties[] = ['type' => 'caution', 'membre_id' => $caution->id, 'montant' => (string) ($ligne['montant'] ?? '')];
                } else {
                    $fichier = $request->file("garanties.{$i}.piece");
                    if (blank($ligne['description'] ?? null) && $fichier === null) {
                        continue;
                    }
                    // Nom aléatoire, disque local hors du dossier public (CLAUDE.md §5).
                    $chemin = $fichier?->store('garanties', 'local');
                    if (is_string($chemin)) {
                        $pieces[] = $chemin;
                    }
                    $garanties[] = ['type' => 'bien', 'description' => (string) ($ligne['description'] ?? ''),
                        'valeur' => (string) ($ligne['valeur'] ?? ''), 'piece_chemin' => is_string($chemin) ? $chemin : null];
                }
            }

            $pret = $demander->executer($this->acteur($request), $membre, $produit, $compte,
                Montant::depuisSaisie($donnees['montant'], $produit->devise), (int) $donnees['nombre_echeances'],
                MethodeCalcul::from($donnees['methode']), $donnees['objet'], $garanties);
        } catch (CreditRefuse $e) {
            Storage::disk('local')->delete($pieces);

            return back()->withInput()->withErrors(['demande' => $e->getMessage()]);
        }

        return to_route('prets.show', $pret)->with('statut', "Demande {$pret->numero} enregistrée : analysez-la pour la soumettre au comité.");
    }

    public function show(Pret $pret): View
    {
        Gate::authorize('view', $pret);
        $pret->load(['membre', 'produit', 'compteEpargne', 'garanties.membre:id,numero,nom,postnom,prenom']);
        $echeances = EcheancePret::query()->where('pret_id', $pret->id)->orderBy('rang')->get();
        $zero = Montant::zero($pret->devise);
        $acteurs = User::query()->whereIn('id', array_filter([$pret->saisi_par_id, $pret->comite_par_id, $pret->gerant_par_id,
            $pret->getAttribute('rejete_par_id'), $pret->decaisse_par_id]))->pluck('name', 'id');

        return view('credit.pret', [
            'pret' => $pret,
            'echeances' => $echeances,
            'resteDu' => $echeances->reduce(fn (Montant $t, EcheancePret $e) => $t->plus($e->resteDu()), $zero),
            'capitalRestant' => $echeances->reduce(fn (Montant $t, EcheancePret $e) => $t->plus($e->capitalRestant()), $zero),
            'remboursements' => Remboursement::query()->with('recu')->where('pret_id', $pret->id)->latest('id')->paginate(20),
            'acteurs' => $acteurs,
            'cle' => (string) Str::uuid(),
            'saisie' => SaisieGarantie::query()->where('pret_id', $pret->id)->where('statut', 'en_attente')->first(),
        ]);
    }

    public function analyser(Request $request, Pret $pret, AnalyserPret $analyser): RedirectResponse
    {
        Gate::authorize('analyser', $pret);
        $analyse = $request->validate(['analyse' => ['required', 'string', 'max:5000']])['analyse'];

        try {
            $analyser->executer($this->acteur($request), $pret, $analyse);
        } catch (CreditRefuse $e) {
            return back()->withErrors(['analyse' => $e->getMessage()]);
        }

        return to_route('prets.show', $pret)->with('statut', 'Demande soumise au comité.');
    }

    public function decider(Request $request, Pret $pret, DeciderPret $decider, string $decision): RedirectResponse
    {
        Gate::authorize('decider', $pret);
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];

        try {
            $pret = $decision === 'approuver' ? $decider->approuver($this->acteur($request), $pret, $motif) : $decider->rejeter($this->acteur($request), $pret, $motif);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['motif' => $e->getMessage()]);
        }

        return to_route('prets.show', $pret)->with('statut', match ($pret->statut) {
            StatutPret::AttenteGerant => 'Approuvé par le comité : le gérant doit approuver à son tour (montant au-delà du seuil).',
            StatutPret::Approuve => 'Prêt approuvé : il peut être décaissé.',
            default => 'Demande rejetée.',
        });
    }

    public function decaisser(Request $request, Pret $pret, DecaisserPret $decaisser): RedirectResponse
    {
        Gate::authorize('decaisser', $pret);

        try {
            $decaisser->executer($this->acteur($request), $pret);
        } catch (CreditRefuse|CompteEpargneRefuse|RefusFinancier $e) {
            return back()->withErrors(['decaissement' => $e->getMessage()]);
        }

        return to_route('prets.show', $pret)->with('statut', "Prêt décaissé sur le compte d'épargne ; échéancier établi.");
    }

    public function rembourser(Request $request, Pret $pret, RembourserPret $rembourser): RedirectResponse
    {
        Gate::authorize('rembourser', $pret);
        $donnees = $request->validate([
            'montant' => ['required', new MontantSaisi],
            'mode' => ['required', Rule::enum(ModeRemboursement::class)],
            'cle' => ['required', 'uuid'],
        ], ['cle.*' => 'Formulaire expiré : rechargez la page.']);

        try {
            $r = $rembourser->executer($this->acteur($request), $pret, Montant::depuisSaisie($donnees['montant'], $pret->devise),
                ModeRemboursement::from($donnees['mode']), $donnees['cle']);
        } catch (CreditRefuse|CompteEpargneRefuse|RefusFinancier $e) {
            return back()->withInput()->withErrors(['montant' => $e->getMessage()]);
        }

        return to_route('prets.show', $pret)->with('statut', "Remboursement de {$r->montant->formate()} enregistré.");
    }

    public function saisirGarantie(Request $request, Pret $pret, DemanderSaisieGarantie $demander): RedirectResponse
    {
        Gate::authorize('saisirGarantie', $pret);
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];

        try {
            $demander->executer($this->acteur($request), $pret, $motif);
        } catch (CreditRefuse $e) {
            return back()->withErrors(['saisie' => $e->getMessage()]);
        }

        return to_route('prets.show', $pret)->with('statut', 'Prélèvement de la garantie demandé : le comptable doit le valider.');
    }

    public function deciderSaisie(Request $request, SaisieGarantie $saisie, DeciderSaisieGarantie $decider, string $decision): RedirectResponse
    {
        Gate::authorize('validerSaisie', $saisie);
        $motif = $request->validate(['motif' => ['required', 'string', 'max:500']])['motif'];

        try {
            $decision === 'valider' ? $decider->valider($this->acteur($request), $saisie, $motif) : $decider->rejeter($this->acteur($request), $saisie, $motif);
        } catch (CreditRefuse|CompteEpargneRefuse|RefusFinancier|InvalidArgumentException $e) {
            return back()->withErrors(['saisie' => $e->getMessage()]);
        }

        return to_route('prets.show', $saisie->pret_id)->with('statut', $decision === 'valider' ? 'Garantie prélevée et imputée sur les échéances en retard.' : 'Prélèvement rejeté.');
    }

    public function piece(GarantiePret $garantie): StreamedResponse
    {
        $pret = Pret::query()->findOrFail($garantie->getAttribute('pret_id'));
        Gate::authorize('view', $pret);
        $chemin = $garantie->getAttribute('piece_chemin');
        abort_unless(is_string($chemin) && Storage::disk('local')->exists($chemin), 404);

        return Storage::disk('local')->download($chemin, "garantie-{$pret->numero}-{$garantie->id}.".pathinfo($chemin, PATHINFO_EXTENSION));
    }

    private function acteur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
