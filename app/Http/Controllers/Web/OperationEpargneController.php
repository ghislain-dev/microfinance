<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Change\Actions\Convertir;
use App\Domain\Change\Models\TauxChange;
use App\Domain\Epargne\Actions\Deposer;
use App\Domain\Epargne\Actions\Retirer;
use App\Domain\Epargne\Actions\Virer;
use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Exceptions\ValidationRequise;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\DemandeDecaissement;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\StatutDemande;
use App\Domain\Finance\Exceptions\RefusFinancier;
use App\Domain\Finance\MoteurFinancier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Epargne\OperationEpargneRequest;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OperationEpargneController extends Controller
{
    public function show(CompteEpargne $compteEpargne, MoteurFinancier $moteur): View
    {
        Gate::authorize('view', $compteEpargne);
        $compteEpargne->load(['membre', 'type', 'compte']);

        return view('epargne.compte', [
            'compte' => $compteEpargne,
            'solde' => $moteur->soldeReel($compteEpargne->compte),
            'mouvements' => MouvementEpargne::query()->with('recu:id,mouvement_epargne_id,numero')->where('compte_epargne_id', $compteEpargne->id)->latest('id')->paginate(25),
            'aPayer' => DemandeDecaissement::query()->where('compte_epargne_id', $compteEpargne->id)
                ->where('type', 'retrait')->where('statut', StatutDemande::Approuvee->value)->get(),
            'cle' => (string) Str::uuid(),
            // Change : comptes du même membre dans l'autre devise, au taux du jour.
            'destinationsChange' => CompteEpargne::query()->with('type:id,code,devise')
                ->where('membre_id', $compteEpargne->membre_id)->whereKeyNot($compteEpargne->id)->get()
                ->filter(fn (CompteEpargne $c) => $c->type->devise !== $compteEpargne->type->devise)->values(),
            'tauxDuJour' => TauxChange::duJour(),
        ]);
    }

    public function convertir(OperationEpargneRequest $request, CompteEpargne $compteEpargne, Convertir $convertir): RedirectResponse
    {
        $destination = CompteEpargne::query()->find($request->integer('destination_id'));
        if ($destination === null) {
            return back()->withInput()->withErrors(['montant' => 'Choisissez le compte qui recevra la conversion.']);
        }
        Gate::authorize('virer', [CompteEpargne::class, $compteEpargne, $destination]);

        try {
            $conversion = $convertir->executer($this->acteur($request), $compteEpargne, $destination,
                $request->montant($compteEpargne->type->devise), $request->string('cle')->value());
        } catch (CompteEpargneRefuse|RefusFinancier $e) {
            return back()->withInput()->withErrors(['montant' => $e->getMessage()]);
        }

        return to_route('comptes-epargne.show', $compteEpargne)
            ->with('statut', "Change effectué : {$conversion->montant_source->formate()} → {$conversion->montant_destination->formate()}.");
    }

    public function deposer(OperationEpargneRequest $request, CompteEpargne $compteEpargne, Deposer $deposer): RedirectResponse
    {
        Gate::authorize('operer', $compteEpargne);

        return $this->executer($compteEpargne, fn () => $deposer->executer(
            $this->acteur($request), $compteEpargne, $request->montant($compteEpargne->type->devise), $request->string('cle')->value(),
        ), 'Dépôt enregistré.');
    }

    public function retirer(OperationEpargneRequest $request, CompteEpargne $compteEpargne, Retirer $retirer): RedirectResponse
    {
        Gate::authorize('operer', $compteEpargne);

        return $this->executer($compteEpargne, fn () => $retirer->executer(
            $this->acteur($request), $compteEpargne, $request->montant($compteEpargne->type->devise), $request->string('cle')->value(),
        ), 'Retrait enregistré.');
    }

    public function virer(OperationEpargneRequest $request, CompteEpargne $compteEpargne, Virer $virer): RedirectResponse
    {
        $destination = CompteEpargne::query()->where('numero', trim($request->string('destination')->value()))->first();
        if ($destination === null) {
            return back()->withInput()->withErrors(['destination' => 'Aucun compte d’épargne avec ce numéro dans votre organisation.']);
        }
        Gate::authorize('virer', [CompteEpargne::class, $compteEpargne, $destination]);

        return $this->executer($compteEpargne, fn () => $virer->executer(
            $this->acteur($request), $compteEpargne, $destination, $request->montant($compteEpargne->type->devise), $request->string('cle')->value(),
        ), "Virement vers {$destination->numero} enregistré.");
    }

    /**
     * Paiement au guichet d'un retrait validé.
     */
    public function payer(Request $request, DemandeDecaissement $demande, Retirer $retirer): RedirectResponse
    {
        $donnees = $request->validate(['cle' => ['required', 'uuid']]);
        $compte = $demande->compteEpargne;
        Gate::authorize('operer', $compte);

        return $this->executer($compte, fn () => $retirer->executer($this->acteur($request), $compte, $demande->montant, $donnees['cle'], $demande),
            "Retrait de {$demande->montant->formate()} payé.");
    }

    /**
     * @param  Closure(): MouvementEpargne  $operation
     */
    private function executer(CompteEpargne $compte, Closure $operation, string $succes): RedirectResponse
    {
        try {
            $operation();
        } catch (ValidationRequise $e) {
            return to_route('comptes-epargne.show', $compte)->with('statut', $e->getMessage());
        } catch (CompteEpargneRefuse|RefusFinancier $e) {
            return back()->withInput()->withErrors(['montant' => $e->getMessage()]);
        }

        return to_route('comptes-epargne.show', $compte)->with('statut', $succes);
    }

    private function acteur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 401);

        return $utilisateur;
    }
}
