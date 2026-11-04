<?php

declare(strict_types=1);

namespace App\Domain\Rapports\Jobs;

use App\Domain\Organisation\Models\Organisation;
use App\Domain\Rapports\Models\Export;
use App\Domain\Rapports\Services\EcrivainCsv;
use App\Domain\Rapports\Services\EcrivainPdf;
use App\Domain\Rapports\Services\FabriqueTableaux;
use App\Models\User;
use App\Support\Temps\Horloge;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Génère un rapport en file d'attente (CLAUDE.md §6), dans l'organisation de son auteur : l'auteur est
 * « connecté » le temps du calcul pour que chaque lecture reste filtrée sur son organisation.
 */
final class GenererExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public readonly int $exportId) {}

    public function handle(FabriqueTableaux $fabrique, EcrivainCsv $csv, EcrivainPdf $pdf): void
    {
        $export = Export::withoutGlobalScopes()->findOrFail($this->exportId);
        $auteur = User::withoutGlobalScopes()->findOrFail($export->user_id);
        // File synchrone (tests, poste unique) : on rend la main avec l'utilisateur d'avant.
        $precedent = Auth::user();
        $equipe = getPermissionsTeamId();
        Auth::setUser($auteur);
        setPermissionsTeamId($auteur->organisation_id);

        try {
            $tableau = $fabrique->pour($export);
            $contenu = $export->format === 'pdf' ? $pdf->ecrire($tableau, Organisation::query()->findOrFail($auteur->organisation_id)) : $csv->ecrire($tableau);
            $chemin = "exports/{$export->organisation_id}/".Str::uuid().".{$export->format}";
            Storage::disk('local')->put($chemin, $contenu);
            $export->forceFill([
                'statut' => 'pret', 'chemin' => $chemin,
                'nom_fichier' => Str::slug($export->libelle()).'-'.Horloge::local(now(), $export->organisation_id)->format('Ymd-His').".{$export->format}",
            ])->save();
        } catch (Throwable $e) {
            $export->forceFill(['statut' => 'echec', 'erreur' => mb_substr($e->getMessage(), 0, 500)])->save();
            throw $e;
        } finally {
            $precedent instanceof User ? Auth::setUser($precedent) : Auth::forgetUser();
            setPermissionsTeamId($equipe);
        }
    }
}
