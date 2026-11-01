<?php

declare(strict_types=1);

namespace App\Domain\Credit\Actions;

use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\SeuilCredit;
use App\Domain\Credit\StatutPret;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Approbation à deux niveaux (ADR 021) : un membre du comité, puis le gérant si le montant dépasse le seuil
 * de l'organisation pour la devise. Un rejet, à l'un ou l'autre niveau, clôt la demande.
 */
final class DeciderPret
{
    public function approuver(User $acteur, Pret $pret, string $motif): Pret
    {
        $motif = $this->motif($motif);

        return DB::transaction(function () use ($acteur, $pret, $motif) {
            $pret = Pret::query()->whereKey($pret->id)->lockForUpdate()->firstOrFail();
            AutoriserDansOrganisation::autoriser($acteur, 'decider', $pret);

            if ($pret->statut === StatutPret::Soumis) {
                $pret->forceFill([
                    'comite_par_id' => $acteur->id, 'comite_le' => now(), 'motif_decision' => $motif,
                    'statut' => $this->depasseSeuil($pret) ? StatutPret::AttenteGerant : StatutPret::Approuve,
                ]);
            } else {
                $pret->forceFill(['gerant_par_id' => $acteur->id, 'gerant_le' => now(), 'motif_decision' => $motif, 'statut' => StatutPret::Approuve]);
            }
            $pret->save();

            return $pret;
        });
    }

    public function rejeter(User $acteur, Pret $pret, string $motif): Pret
    {
        $motif = $this->motif($motif);

        return DB::transaction(function () use ($acteur, $pret, $motif) {
            $pret = Pret::query()->whereKey($pret->id)->lockForUpdate()->firstOrFail();
            AutoriserDansOrganisation::autoriser($acteur, 'decider', $pret);
            $pret->forceFill(['rejete_par_id' => $acteur->id, 'motif_decision' => $motif, 'statut' => StatutPret::Rejete])->save();

            return $pret;
        });
    }

    private function depasseSeuil(Pret $pret): bool
    {
        $seuil = SeuilCredit::query()->where('devise', $pret->devise->value)->first();

        return $seuil !== null && $pret->montant->comparer($seuil->montant) > 0;
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
