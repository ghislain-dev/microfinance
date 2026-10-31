<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Recus;

use App\Domain\Epargne\Models\Recu;
use App\Models\User;
use App\Support\Montant\MontantEnLettres;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Génère le PDF A5 d'un reçu, en file d'attente (jamais pendant la requête HTTP, CLAUDE.md §6).
 */
final class GenererRecuPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly int $recuId)
    {
        $this->afterCommit = true; // le reçu n'existe qu'une fois l'opération validée
    }

    public function handle(): void
    {
        $recu = $this->recu();
        if ($recu->chemin !== null) {
            return; // déjà généré (tâche rejouée)
        }

        $chemin = "recus/{$recu->organisation_id}/".Str::random(40).'.pdf';
        Storage::disk('local')->put($chemin, Pdf::loadHTML($this->html())->setPaper('a5')
            ->setOption('isFontSubsettingEnabled', true) // seules les lettres utilisées : quelques Ko au lieu de 1 Mo
            ->output());
        $recu->forceFill(['chemin' => $chemin])->save();
    }

    public function html(): string
    {
        $recu = $this->recu();
        $mouvement = $recu->mouvement;
        $compte = $mouvement->compteEpargne;
        $organisation = $compte->membre->organisation;
        $logo = $organisation?->logo_chemin !== null && Storage::disk('local')->exists($organisation->logo_chemin)
            ? 'data:'.Storage::disk('local')->mimeType($organisation->logo_chemin).';base64,'.base64_encode((string) Storage::disk('local')->get($organisation->logo_chemin))
            : null;

        return view('recus.recu', [
            'recu' => $recu,
            'mouvement' => $mouvement,
            'compte' => $compte,
            'organisation' => $organisation,
            'logo' => $logo,
            'enLettres' => MontantEnLettres::ecrire($mouvement->montant),
            'caissier' => User::withoutGlobalScopes()->find($mouvement->getAttribute('auteur_id')),
        ])->render();
    }

    private function recu(): Recu
    {
        return Recu::withoutGlobalScopes()
            ->with(['mouvement.compteEpargne.membre.organisation', 'mouvement.compteEpargne.membre.agence', 'mouvement.compteEpargne.type'])
            ->findOrFail($this->recuId);
    }
}
