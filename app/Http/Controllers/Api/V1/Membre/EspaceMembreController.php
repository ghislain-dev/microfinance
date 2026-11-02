<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Membre;

use App\Domain\Credit\Models\Pret;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\Services\Blocages;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Seance;
use App\Domain\Tontines\Models\Tour;
use App\Domain\Tontines\Services\Dettes;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\StatutSeance;
use App\Http\Controllers\Controller;
use App\Http\Resources\Membre\CompteMembreResource;
use App\Http\Resources\Membre\MouvementResource;
use App\Http\Resources\Membre\PretMembreResource;
use App\Http\Resources\MontantJson;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Données du membre connecté (jeton « membre ») : le membre vient toujours du jeton, jamais de la requête.
 */
class EspaceMembreController extends Controller
{
    public function comptes(Request $request, MoteurFinancier $moteur, Blocages $blocages): AnonymousResourceCollection
    {
        $comptes = CompteEpargne::query()->with(['type', 'compte'])->where('membre_id', $this->membre($request)->id)->orderBy('id')->get();

        return CompteMembreResource::collection($comptes->map(function (CompteEpargne $compte) use ($moteur, $blocages) {
            $solde = $moteur->soldeReel($compte->compte);

            return ['compte' => $compte, 'solde' => $solde, 'bloque' => $blocages->total($compte), 'disponible' => $blocages->disponible($compte, $solde)];
        }));
    }

    public function mouvements(Request $request, int $compte): AnonymousResourceCollection
    {
        $depuis = $request->validate(['depuis' => ['nullable', 'date']], ['depuis.date' => 'depuis : date ISO 8601 attendue.'])['depuis'] ?? null;
        $compteEpargne = CompteEpargne::query()->where('membre_id', $this->membre($request)->id)->whereKey($compte)->firstOrFail();

        return MouvementResource::collection(MouvementEpargne::query()->with('recu')->where('compte_epargne_id', $compteEpargne->id)
            ->when($depuis !== null, fn ($q) => $q->where('created_at', '>', CarbonImmutable::parse((string) $depuis)->utc()))
            ->orderByDesc('id')->cursorPaginate(50));
    }

    public function prets(Request $request): AnonymousResourceCollection
    {
        return PretMembreResource::collection(Pret::query()->with('echeances')->where('membre_id', $this->membre($request)->id)
            ->latest('id')->paginate(20));
    }

    public function tontines(Request $request, Dettes $dettes): JsonResponse
    {
        $membre = $this->membre($request);
        $cycles = Cycle::query()->with('groupe')->where('statut', StatutCycle::EnCours->value)
            ->whereHas('participants', fn ($q) => $q->whereKey($membre->id))->orderBy('id')->get();

        return response()->json(['data' => $cycles->map(function (Cycle $cycle) use ($membre, $dettes) {
            $prochaine = Seance::query()->where('cycle_id', $cycle->id)->where('statut', StatutSeance::Prevue->value)->orderBy('rang')->first();
            $tour = Tour::query()->where('cycle_id', $cycle->id)->where('membre_id', $membre->id)->first();
            $seanceDuTour = $tour === null ? null : Seance::query()->where('cycle_id', $cycle->id)->where('rang', $tour->getAttribute('rang'))->first();

            return [
                'cycle_id' => $cycle->id,
                'groupe' => ['code' => $cycle->groupe->code, 'nom' => $cycle->groupe->nom, 'type' => $cycle->groupe->type->value],
                'numero' => $cycle->numero,
                'periodicite' => $cycle->periodicite->libelle(),
                'cotisation' => MontantJson::de($cycle->montant_cotisation),
                'fonds_social' => MontantJson::de($cycle->cotisation_fonds_social),
                'prochaine_seance' => $prochaine === null ? null : ['rang' => $prochaine->getAttribute('rang'), 'date' => $prochaine->date_prevue->toDateString()],
                'mon_tour' => $tour === null ? null : ['rang' => $tour->getAttribute('rang'), 'date' => $seanceDuTour?->date_prevue->toDateString()],
                'dette' => MontantJson::de($dettes->du($cycle, $membre)),
            ];
        })->values()->all()]);
    }

    private function membre(Request $request): Membre
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, Response::HTTP_UNAUTHORIZED);
        $membre = $utilisateur->membre;
        abort_if($membre === null, Response::HTTP_FORBIDDEN);

        return $membre;
    }
}
