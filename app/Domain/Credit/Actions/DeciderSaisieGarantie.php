<?php

declare(strict_types=1);

namespace App\Domain\Credit\Actions;

use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\SaisieGarantie;
use App\Domain\Credit\Services\MontantSaisissable;
use App\Domain\Epargne\Services\Blocages;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Le comptable valide le prélèvement (jamais l'auteur) : montant recalculé, part de garantie libérée, puis
 * remboursement depuis l'épargne avec l'imputation habituelle (pénalités → intérêts → capital).
 */
final class DeciderSaisieGarantie
{
    public function __construct(
        private readonly MontantSaisissable $saisissable,
        private readonly Blocages $blocages,
        private readonly RembourserPret $rembourser,
    ) {}

    public function valider(User $comptable, SaisieGarantie $saisie, string $motif): SaisieGarantie
    {
        $motif = $this->motif($motif);

        return DB::transaction(function () use ($comptable, $saisie, $motif) {
            $saisie = SaisieGarantie::query()->whereKey($saisie->id)->lockForUpdate()->firstOrFail();
            AutoriserDansOrganisation::autoriser($comptable, 'validerSaisie', $saisie);
            $pret = Pret::query()->findOrFail($saisie->pret_id);
            $montant = $this->saisissable->pour($pret); // la situation a pu changer depuis la demande

            $this->blocages->reduire("pret:{$pret->id}", $montant);
            $remboursement = $this->rembourser->prelevementSurGarantie($pret, $montant, "saisie-garantie:{$saisie->id}");
            $saisie->forceFill(['statut' => 'validee', 'montant' => $montant, 'decide_par_id' => $comptable->id,
                'motif_decision' => $motif, 'remboursement_id' => $remboursement->id])->save();

            return $saisie;
        });
    }

    public function rejeter(User $comptable, SaisieGarantie $saisie, string $motif): SaisieGarantie
    {
        $motif = $this->motif($motif);
        AutoriserDansOrganisation::autoriser($comptable, 'validerSaisie', $saisie);
        $saisie->forceFill(['statut' => 'rejetee', 'decide_par_id' => $comptable->id, 'motif_decision' => $motif])->save();

        return $saisie;
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
