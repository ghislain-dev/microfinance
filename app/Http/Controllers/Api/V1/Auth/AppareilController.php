<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppareilResource;
use App\Http\Resources\UtilisateurResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AppareilController extends Controller
{
    public function moi(Request $request): UtilisateurResource
    {
        return new UtilisateurResource($this->utilisateur($request));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return AppareilResource::collection($this->utilisateur($request)->tokens()->latest('id')->paginate(50));
    }

    /**
     * Révoque un de ses propres jetons ; celui d'un autre utilisateur donne 404.
     */
    public function destroy(Request $request, int $id): Response
    {
        $this->utilisateur($request)->tokens()->whereKey($id)->firstOrFail()->delete();

        return response()->noContent();
    }

    private function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, Response::HTTP_UNAUTHORIZED);

        return $utilisateur;
    }
}
