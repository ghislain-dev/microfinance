<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Actions;

use App\Domain\Epargne\Exceptions\CompteEpargneRefuse;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\TypeCompteEpargne;
use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Membres\Models\Membre;
use App\Domain\Membres\StatutMembre;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Temps\Horloge;
use Illuminate\Support\Facades\DB;

/**
 * Ouvre un compte d'épargne : numéro « membre-TYPE-rang », compte de grand livre de nature épargne,
 * échéance de blocage = ouverture + durée du type.
 */
final class OuvrirCompteEpargne
{
    public function __construct(private readonly OuvrirCompte $ouvrirCompte) {}

    public function executer(User $acteur, Membre $membre, TypeCompteEpargne $type): CompteEpargne
    {
        AutoriserDansOrganisation::autoriser($acteur, 'create', [CompteEpargne::class, $membre, $type]);

        if ($membre->statut !== StatutMembre::Actif) {
            throw new CompteEpargneRefuse("Membre {$membre->statut->libelle()} : ouverture de compte impossible.");
        }
        if (! $type->actif) {
            throw new CompteEpargneRefuse("Le type {$type->code} n'est plus proposé.");
        }

        return DB::transaction(function () use ($membre, $type) {
            // Verrou sur le membre : deux ouvertures simultanées ne prennent pas le même rang.
            $membre = Membre::query()->with('agence')->whereKey($membre->id)->lockForUpdate()->firstOrFail();
            $rang = (int) CompteEpargne::query()->where('membre_id', $membre->id)
                ->where('type_compte_epargne_id', $type->id)->max('rang') + 1;
            $numero = "{$membre->numero}-{$type->code}-{$rang}";

            $compte = $this->ouvrirCompte->executer($numero, "{$type->libelle} — {$membre->nomComplet()}",
                NatureCompte::Epargne, $type->devise, $membre->agence);

            $epargne = new CompteEpargne;
            $epargne->forceFill([
                'organisation_id' => $membre->organisation_id,
                'membre_id' => $membre->id,
                'type_compte_epargne_id' => $type->id,
                'compte_id' => $compte->id,
                'numero' => $numero,
                'rang' => $rang,
                'date_ouverture' => Horloge::aujourdhui($membre->organisation_id)->toDateString(),
                'bloque_jusqu_au' => $type->duree_blocage_mois === null
                    ? null : Horloge::aujourdhui($membre->organisation_id)->addMonthsNoOverflow($type->duree_blocage_mois)->toDateString(),
            ])->save();

            return $epargne->load('compte');
        });
    }
}
