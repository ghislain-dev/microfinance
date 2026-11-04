<?php

declare(strict_types=1);

namespace App\Domain\Credit\Actions;

use App\Domain\Credit\CreditRefuse;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\Models\SaisieGarantie;
use App\Domain\Credit\Services\MontantSaisissable;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Le gérant demande le prélèvement de la garantie d'un prêt en défaut ; le comptable valide (ADR 021).
 */
final class DemanderSaisieGarantie
{
    public function __construct(private readonly MontantSaisissable $saisissable) {}

    public function executer(User $gerant, Pret $pret, string $motif): SaisieGarantie
    {
        AutoriserDansOrganisation::autoriser($gerant, 'saisirGarantie', $pret);
        $motif = trim($motif);
        if ($motif === '' || mb_strlen($motif) > 500) {
            throw new CreditRefuse('Motif obligatoire (500 caractères au plus).');
        }
        $montant = $this->saisissable->pour($pret);

        $saisie = new SaisieGarantie;
        try {
            $saisie->forceFill([
                'organisation_id' => $pret->organisation_id, 'pret_id' => $pret->id, 'devise' => $pret->devise, 'montant' => $montant,
                'motif' => $motif, 'statut' => 'en_attente', 'demande_par_id' => $gerant->id,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            throw new CreditRefuse('Un prélèvement de la garantie attend déjà la validation du comptable.');
        }

        return $saisie;
    }
}
