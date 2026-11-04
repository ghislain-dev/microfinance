<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Actions;

use App\Domain\Comptabilite\ClotureRefusee;
use App\Domain\Comptabilite\Models\DemandeCloture;
use App\Domain\Comptabilite\Services\ControlesCloture;
use App\Domain\Finance\Actions\CloturerPeriode;
use App\Domain\Organisation\Models\Organisation;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Le gérant valide (contrôles refaits, puis clôture définitive) ou rejette la demande ; jamais son auteur.
 */
final class DeciderCloture
{
    public function __construct(private readonly ControlesCloture $controles, private readonly CloturerPeriode $cloturer) {}

    public function valider(User $gerant, DemandeCloture $demande, string $motif): DemandeCloture
    {
        $motif = $this->motif($motif);

        return DB::transaction(function () use ($gerant, $demande, $motif) {
            $demande = DemandeCloture::query()->whereKey($demande->id)->lockForUpdate()->firstOrFail();
            AutoriserDansOrganisation::autoriser($gerant, 'validerCloture', $demande);
            $organisation = Organisation::query()->findOrFail($demande->organisation_id);
            $fin = CarbonImmutable::parse($demande->date_fin);
            $problemes = $this->controles->verifier($organisation, $fin);
            if ($problemes !== []) {
                throw new ClotureRefusee('Clôture impossible :', $problemes);
            }

            try {
                $this->cloturer->executer($organisation, $fin, "demande:{$demande->id}:valide_par:{$gerant->id}");
            } catch (InvalidArgumentException $e) {
                throw new ClotureRefusee($e->getMessage());
            }
            $demande->forceFill(['statut' => 'validee', 'decide_par_id' => $gerant->id, 'motif_decision' => $motif, 'decide_le' => now()])->save();

            return $demande;
        });
    }

    public function rejeter(User $gerant, DemandeCloture $demande, string $motif): DemandeCloture
    {
        $motif = $this->motif($motif);
        AutoriserDansOrganisation::autoriser($gerant, 'validerCloture', $demande);
        $demande->forceFill(['statut' => 'rejetee', 'decide_par_id' => $gerant->id, 'motif_decision' => $motif, 'decide_le' => now()])->save();

        return $demande;
    }

    private function motif(string $motif): string
    {
        $motif = trim($motif);
        if ($motif === '' || mb_strlen($motif) > 500) {
            throw new InvalidArgumentException('Motif obligatoire (500 caractères au plus).');
        }

        return $motif;
    }
}
