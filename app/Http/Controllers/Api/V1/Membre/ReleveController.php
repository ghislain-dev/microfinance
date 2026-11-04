<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Membre;

use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Rapports\Actions\DemanderExport;
use App\Domain\Rapports\Models\Export;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Relevé de compte dans l'application du membre : demandé (202), préparé en file d'attente, puis téléchargé.
 */
class ReleveController extends Controller
{
    public function store(Request $request, int $compte, DemanderExport $demander): JsonResponse
    {
        $utilisateur = $this->utilisateur($request);
        $donnees = $request->validate(['du' => ['required', 'date_format:Y-m-d'], 'au' => ['required', 'date_format:Y-m-d', 'after_or_equal:du']]);
        $compteEpargne = CompteEpargne::query()->where('membre_id', $utilisateur->membreId())->whereKey($compte)->firstOrFail();

        try {
            $export = $demander->executer($utilisateur, 'releve', 'pdf', ['compte_epargne_id' => $compteEpargne->id, ...$donnees]);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['du' => $e->getMessage()]);
        }

        return response()->json(['id' => $export->id, 'statut' => $export->refresh()->statut], Response::HTTP_ACCEPTED);
    }

    public function show(Request $request, int $releve): JsonResponse|StreamedResponse
    {
        $export = Export::query()->where('user_id', $this->utilisateur($request)->id)->where('type', 'releve')->whereKey($releve)->firstOrFail();
        if ($export->statut !== 'pret') {
            return response()->json(['id' => $export->id, 'statut' => $export->statut], Response::HTTP_ACCEPTED);
        }

        return Storage::disk('local')->download((string) $export->getAttribute('chemin'), (string) $export->getAttribute('nom_fichier'), ['Content-Type' => 'application/pdf']);
    }

    private function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User && $utilisateur->membreId() !== null, Response::HTTP_FORBIDDEN);

        return $utilisateur;
    }
}
