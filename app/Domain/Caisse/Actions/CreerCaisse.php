<?php

declare(strict_types=1);

namespace App\Domain\Caisse\Actions;

use App\Domain\Caisse\Models\Caisse;
use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Organisation\Models\Agence;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Devise;
use Illuminate\Support\Facades\DB;

/**
 * Crée une caisse (guichet) et ses comptes de caisse, un par devise.
 */
final class CreerCaisse
{
    public function __construct(private readonly OuvrirCompte $ouvrirCompte) {}

    public function executer(User $acteur, Agence $agence, string $code, string $libelle): Caisse
    {
        AutoriserDansOrganisation::autoriser($acteur, 'create', [Caisse::class, $agence]);

        return DB::transaction(function () use ($agence, $code, $libelle) {
            $caisse = new Caisse(['code' => $code, 'libelle' => $libelle]);
            $caisse->forceFill(['organisation_id' => $agence->organisation_id, 'agence_id' => $agence->id])->save();

            foreach (Devise::cases() as $devise) {
                $compte = $this->ouvrirCompte->executer("CAISSE-{$code}-{$devise->value}", "Caisse {$libelle} ({$devise->value})", NatureCompte::Caisse, $devise, $agence);
                $caisse->comptes()->attach($compte, ['devise' => $devise->value, 'organisation_id' => $agence->organisation_id]);
            }

            return $caisse->refresh(); // valeurs par défaut de la base (actif)
        });
    }
}
