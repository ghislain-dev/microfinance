<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Actions;

use App\Domain\Finance\Actions\OuvrirCompte;
use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Membres\StatutMembre;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Groupe;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\TontineRefusee;
use App\Domain\Tontines\TypeGroupe;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Démarre un cycle : participants figés (membres actifs du groupe), comptes du cycle ouverts
 * (fonds, fonds social, créances de pénalités). Rotative : une séance par participant.
 */
final class DemarrerCycle
{
    public function __construct(private readonly OuvrirCompte $ouvrirCompte) {}

    public function executer(User $acteur, Cycle $cycle): Cycle
    {
        AutoriserDansOrganisation::autoriser($acteur, 'update', $cycle);

        try {
            return DB::transaction(function () use ($cycle) {
                $groupe = Groupe::query()->with('agence')->whereKey($cycle->groupe_id)->lockForUpdate()->firstOrFail();
                $cycle = Cycle::query()->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
                if ($cycle->statut !== StatutCycle::EnPreparation) {
                    throw new TontineRefusee('Ce cycle a déjà démarré.');
                }
                if ($groupe->cycles()->where('statut', StatutCycle::EnCours->value)->exists()) {
                    throw new TontineRefusee("Le groupe {$groupe->code} a déjà un cycle en cours.");
                }
                $participants = $groupe->membres()->where('statut', StatutMembre::Actif->value)->pluck('membres.id');
                if ($participants->count() < 2) {
                    throw new TontineRefusee('Il faut au moins deux membres actifs pour démarrer un cycle.');
                }

                $prefixe = "TONT-{$groupe->code}-C{$cycle->numero}";
                $ouvrir = fn (string $suffixe, string $libelle, NatureCompte $nature) => $this->ouvrirCompte->executer(
                    "{$prefixe}-{$suffixe}", "{$libelle} — {$groupe->nom} cycle {$cycle->numero}", $nature, $cycle->devise, $groupe->agence);

                $cycle->forceFill([
                    'statut' => StatutCycle::EnCours,
                    'nombre_seances' => $groupe->type === TypeGroupe::Rotative ? $participants->count() : $cycle->nombre_seances,
                    'compte_fonds_id' => $ouvrir('FONDS', 'Fonds du cycle', NatureCompte::CycleTontine)->id,
                    'compte_fonds_social_id' => $ouvrir('FS', 'Fonds social', NatureCompte::FondsSocial)->id,
                    'compte_creances_id' => $ouvrir('CREANCES', 'Pénalités dues', NatureCompte::CreanceMembre)->id,
                ])->save();
                $cycle->participants()->attach($participants->mapWithKeys(fn (int $id) => [$id => ['organisation_id' => $cycle->organisation_id]])->all());

                return $cycle;
            });
        } catch (UniqueConstraintViolationException) {
            throw new TontineRefusee('Le groupe a déjà un cycle en cours.');
        }
    }
}
