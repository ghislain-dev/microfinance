<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Collecte;

use App\Domain\Collecte\Services\ServiceTournee;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TourneeController extends Controller
{
    /**
     * Tournée du collecteur connecté ; avec ?depuis=, seulement les membres modifiés et ceux retirés.
     */
    public function show(Request $request, ServiceTournee $tournee): JsonResponse
    {
        $collecteur = $request->user();
        abort_unless($collecteur instanceof User, Response::HTTP_UNAUTHORIZED);
        $depuis = $request->validate(['depuis' => ['nullable', 'date']], ['depuis.date' => 'depuis : date ISO 8601 attendue.'])['depuis'] ?? null;

        return response()->json($tournee->pour($collecteur, $depuis === null ? null : CarbonImmutable::parse((string) $depuis)->utc()));
    }
}
