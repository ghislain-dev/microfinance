<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Membre;

use App\Domain\Notifications\Models\JetonPush;
use App\Domain\Notifications\Models\NotificationPush;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationsController extends Controller
{
    /**
     * Enregistre le jeton Firebase du téléphone (un jeton passe au dernier utilisateur qui l'annonce).
     */
    public function jeton(Request $request): Response
    {
        $utilisateur = $this->utilisateur($request);
        $donnees = $request->validate(['jeton' => ['required', 'string', 'max:255'], 'plateforme' => ['required', 'in:android']]);

        $jeton = JetonPush::withoutGlobalScopes()->where('jeton', $donnees['jeton'])->first() ?? new JetonPush;
        $jeton->forceFill(['organisation_id' => $utilisateur->organisation_id, 'user_id' => $utilisateur->id,
            'jeton' => $donnees['jeton'], 'plateforme' => $donnees['plateforme']])->save();

        return response()->noContent();
    }

    public function index(Request $request): JsonResponse
    {
        $depuis = $request->validate(['depuis' => ['nullable', 'date']])['depuis'] ?? null;
        $page = NotificationPush::query()->where('user_id', $this->utilisateur($request)->id)
            ->when($depuis !== null, fn ($q) => $q->where('created_at', '>', CarbonImmutable::parse((string) $depuis)->utc()))
            ->orderByDesc('id')->cursorPaginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (NotificationPush $n) => [
                'id' => $n->id, 'titre' => $n->titre, 'corps' => $n->corps, 'donnees' => $n->donnees, 'le' => $n->created_at?->toIso8601String(),
            ])->values()->all(),
            'suivant' => $page->nextCursor()?->encode(),
        ]);
    }

    private function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, Response::HTTP_UNAUTHORIZED);

        return $utilisateur;
    }
}
