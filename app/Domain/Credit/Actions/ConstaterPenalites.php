<?php

declare(strict_types=1);

namespace App\Domain\Credit\Actions;

use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\StatutPret;
use App\Domain\Finance\LigneEcriture;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Organisation\Models\Organisation;
use App\Support\Montant\Montant;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pénalités de retard (ADR 021) : chaque jour au-delà du délai de grâce, pourcentage journalier du prêt sur
 * le capital impayé de l'échéance, sans plafond. Débit « pénalités dues » du prêt, crédit produit pénalités.
 * Idempotent : une opération par échéance et par jour (clé penalite:{echeance}:{date}) ; les nuits manquées
 * sont rattrapées.
 */
final class ConstaterPenalites
{
    public function __construct(private readonly MoteurFinancier $moteur) {}

    /**
     * Sans utilisateur connecté : l'organisation est toujours filtrée explicitement.
     *
     * @return int nombre de pénalités constatées
     */
    public function executer(Organisation $organisation, CarbonImmutable $aujourdhui): int
    {
        $nombre = 0;
        Pret::query()->where('organisation_id', $organisation->id)->where('statut', StatutPret::EnCours->value)
            ->orderBy('id')->each(function (Pret $pret) use ($organisation, $aujourdhui, &$nombre) {
                $echeances = EcheancePret::query()->where('organisation_id', $organisation->id)->where('pret_id', $pret->id)
                    ->whereNull('payee_le')->whereColumn('capital_paye', '<', 'capital')
                    ->where('date_echeance', '<', $aujourdhui->subDays($pret->delai_grace_jours)->toDateString())->orderBy('rang')->get();
                foreach ($echeances as $echeance) {
                    $nombre += $this->echeance($organisation, $pret, $echeance->id, $aujourdhui);
                }
            });

        return $nombre;
    }

    private function echeance(Organisation $organisation, Pret $pret, int $echeanceId, CarbonImmutable $aujourdhui): int
    {
        return DB::transaction(function () use ($organisation, $pret, $echeanceId, $aujourdhui) {
            $echeance = EcheancePret::query()->whereKey($echeanceId)->lockForUpdate()->firstOrFail();
            $premier = CarbonImmutable::parse($echeance->date_echeance)->addDays($pret->delai_grace_jours + 1);
            $dernier = $echeance->getAttribute('penalite_constatee_au');
            $jour = $dernier === null ? $premier : CarbonImmutable::parse($dernier)->addDay()->max($premier);

            $journaliere = new Montant(BigInteger::of($echeance->capitalRestant()->centimes)->multipliedBy($pret->penalite_jour_pb)
                ->dividedBy(10_000, RoundingMode::HalfEven)->toInt(), $pret->devise);
            $nombre = 0;
            for (; $jour->lte($aujourdhui); $jour = $jour->addDay()) {
                if ($journaliere->estPositif()) {
                    $this->moteur->executer("penalite:{$echeance->id}:{$jour->toDateString()}", 'penalite_credit', [
                        LigneEcriture::debit($this->compte($organisation, (int) $pret->getAttribute('compte_penalites_id')), $journaliere),
                        LigneEcriture::credit($this->produit($organisation, $pret), $journaliere),
                    ], motif: "Pénalité de retard du {$jour->format('d/m/Y')} — prêt {$pret->numero}, échéance {$echeance->rang}");
                    $echeance->penalite = $echeance->penalite->plus($journaliere);
                    $nombre++;
                }
                $echeance->setAttribute('penalite_constatee_au', $jour->toDateString());
            }
            $echeance->save();

            return $nombre;
        });
    }

    private function compte(Organisation $organisation, int $id): Compte
    {
        return Compte::query()->where('organisation_id', $organisation->id)->whereKey($id)->firstOrFail();
    }

    /**
     * Ouvert au décaissement (l'utilisateur connecté fixe alors l'organisation).
     */
    private function produit(Organisation $organisation, Pret $pret): Compte
    {
        return Compte::query()->where('organisation_id', $organisation->id)
            ->where('numero', "PRODUIT-PENALITES-PRET-{$pret->devise->value}")->firstOrFail();
    }
}
