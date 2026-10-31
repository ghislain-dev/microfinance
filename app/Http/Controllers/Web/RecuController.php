<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Epargne\Models\Recu;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecuController extends Controller
{
    /**
     * Téléchargement via contrôleur + Policy : le fichier reste hors du dossier public.
     */
    public function show(Recu $recu): StreamedResponse|RedirectResponse
    {
        Gate::authorize('view', $recu);

        if ($recu->chemin === null || ! Storage::disk('local')->exists($recu->chemin)) {
            return back()->with('statut', 'Reçu en préparation, réessayez dans un instant.');
        }

        return Storage::disk('local')->download($recu->chemin, "{$recu->numero}.pdf", ['Content-Type' => 'application/pdf']);
    }
}
