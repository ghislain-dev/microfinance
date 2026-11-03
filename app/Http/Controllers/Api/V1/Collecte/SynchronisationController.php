<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Collecte;

use App\Domain\Collecte\Models\Appareil;
use App\Domain\Collecte\Services\Synchronisation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collecte\LotSyncRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SynchronisationController extends Controller
{
    /**
     * POST /api/v1/sync/lots : réponse par opération (cree | deja_traite | rejete).
     */
    public function store(LotSyncRequest $request, Synchronisation $synchronisation): JsonResponse
    {
        $collecteur = $request->user();
        abort_unless($collecteur instanceof User, Response::HTTP_UNAUTHORIZED);
        $appareil = Appareil::query()->findOrFail($collecteur->currentAccessToken()->getAttribute('appareil_id'));
        $lot = $request->string('lot_uuid')->value();

        return response()->json([
            'lot_uuid' => $lot,
            'resultats' => $synchronisation->traiter($collecteur, $appareil, $lot, $request->operationsParOrdreDeSaisie()),
        ]);
    }
}
