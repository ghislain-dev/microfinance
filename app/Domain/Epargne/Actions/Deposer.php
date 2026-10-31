<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Actions;

use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\MouvementEpargne;
use App\Domain\Epargne\Services\RegleEpargne;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\MoteurFinancier;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Montant;
use Illuminate\Support\Facades\DB;

/**
 * Dépôt en espèces : débit caisse de la session, crédit épargne du membre (skill §4).
 */
final class Deposer
{
    public function __construct(private readonly MoteurFinancier $moteur, private readonly RegleEpargne $regles) {}

    public function executer(User $operateur, CompteEpargne $compte, Montant $montant, string $cle): MouvementEpargne
    {
        $compte->loadMissing(['membre', 'type', 'compte']);
        AutoriserDansOrganisation::autoriser($operateur, 'operer', $compte);
        $session = $this->regles->sessionOuverte($operateur);
        $this->regles->exigerDevise($compte, $montant);
        $this->regles->exigerMembreActif($compte, 'dépôt');
        if ($montant->comparer($compte->type->depot_minimum) < 0) {
            throw new CompteEpargneRefuse("Dépôt minimum : {$compte->type->depot_minimum->formate()}.");
        }

        return DB::transaction(function () use ($compte, $montant, $cle, $session) {
            $operation = $this->moteur->executer($cle, 'depot', [
                LigneEcriture::debit($session->caisse->compte($montant->devise), $montant),
                LigneEcriture::credit($compte->compte, $montant),
            ]);

            return $this->regles->enregistrerMouvement($operation->id, $compte, 'depot', $montant, session: $session);
        });
    }
}
