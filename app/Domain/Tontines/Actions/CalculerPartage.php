<?php

declare(strict_types=1);

namespace App\Domain\Tontines\Actions;

use App\Domain\Finance\MoteurFinancier;
use App\Domain\Tontines\Models\Aide;
use App\Domain\Tontines\Models\Cotisation;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\LignePartage;
use App\Domain\Tontines\Models\Partage;
use App\Domain\Tontines\Models\Seance;
use App\Domain\Tontines\Services\Dettes;
use App\Domain\Tontines\Services\EpargneDuMembre;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\StatutSeance;
use App\Domain\Tontines\TontineRefusee;
use App\Domain\Tontines\TypeGroupe;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Montant;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Partage de fin de cycle (skill §5) : le fonds du cycle est réparti au prorata des parts (AVEC) ou à parts
 * égales (rotative), méthode du plus fort reste (Σ = montant exact) ; la part règle d'abord la dette du
 * membre (décision du 2026-10-31), le reste est versé sur son épargne après validation du comité.
 */
final class CalculerPartage
{
    public function __construct(
        private readonly MoteurFinancier $moteur,
        private readonly Dettes $dettes,
        private readonly EpargneDuMembre $epargne,
    ) {}

    public function executer(User $acteur, Cycle $cycle): Partage
    {
        AutoriserDansOrganisation::autoriser($acteur, 'update', $cycle);
        $cycle->loadMissing(['groupe', 'compteFonds']);
        if ($cycle->statut !== StatutCycle::EnCours) {
            throw new TontineRefusee('Le cycle n’est pas en cours.');
        }
        if (Seance::query()->where('cycle_id', $cycle->id)->where('statut', StatutSeance::Prevue->value)->exists()) {
            throw new TontineRefusee('Toutes les séances doivent être tenues avant le partage.');
        }
        if (Aide::query()->where('cycle_id', $cycle->id)->where('statut', 'en_attente')->exists()) {
            throw new TontineRefusee('Des aides du fonds social attendent encore la décision du comité.');
        }

        $total = $this->moteur->soldeReel($cycle->compteFonds);
        $participants = $cycle->participants()->orderBy('membres.id')->get();
        $poids = $cycle->groupe->type === TypeGroupe::Avec
            ? $participants->mapWithKeys(fn ($m) => [$m->id => (int) Cotisation::query()->where('membre_id', $m->id)->where('type', 'parts')
                ->whereIn('seance_id', fn ($q) => $q->select('id')->from('seances_tontine')->where('cycle_id', $cycle->id))->sum('parts')])
            : $participants->mapWithKeys(fn ($m) => [$m->id => 1]);
        $somme = (int) $poids->sum();
        if ($total->estNegatif() || ($total->estPositif() && $somme === 0)) {
            throw new TontineRefusee("Partage impossible : fonds du cycle {$total->formate()}, aucune part achetée.");
        }

        $parts = $this->repartir($total->centimes, $poids->all(), $somme);

        try {
            return DB::transaction(function () use ($acteur, $cycle, $total, $participants, $poids, $parts) {
                $partage = new Partage;
                $partage->forceFill([
                    'organisation_id' => $cycle->organisation_id, 'cycle_id' => $cycle->id, 'devise' => $cycle->devise, 'montant_total' => $total,
                    'statut' => 'brouillon', 'calcule_par_id' => $acteur->id,
                ])->save();

                foreach ($participants as $membre) {
                    $part = $parts[$membre->id] ?? 0;
                    $compensation = min($part, max(0, $this->dettes->du($cycle, $membre)->centimes));
                    if ($part - $compensation > 0) {
                        $this->epargne->exiger($membre->id, $cycle->devise, "membre {$membre->numero}");
                    }
                    (new LignePartage)->forceFill([
                        'organisation_id' => $cycle->organisation_id, 'partage_id' => $partage->id, 'membre_id' => $membre->id,
                        'parts' => $cycle->groupe->type === TypeGroupe::Avec ? $poids[$membre->id] : null,
                        'devise' => $cycle->devise, // avant les montants : MontantCast lit la devise
                        'part' => new Montant($part, $cycle->devise),
                        'compensation' => new Montant($compensation, $cycle->devise),
                        'verse' => new Montant($part - $compensation, $cycle->devise),
                    ])->save();
                }

                return $partage;
            });
        } catch (UniqueConstraintViolationException) {
            throw new TontineRefusee('Un partage est déjà calculé pour ce cycle (rejetez-le pour recalculer).');
        }
    }

    /**
     * Méthode du plus fort reste : parts entières, puis un centime aux plus forts restes (à égalité : plus
     * gros poids, puis plus petit identifiant).
     *
     * @param  array<int, int>  $poids
     * @return array<int, int>
     */
    private function repartir(int $total, array $poids, int $somme): array
    {
        if ($total === 0) {
            return array_map(fn () => 0, $poids);
        }
        $parts = $restes = [];
        foreach ($poids as $id => $p) {
            $produit = BigInteger::of($total)->multipliedBy($p);
            $parts[$id] = $produit->dividedBy($somme, RoundingMode::Down)->toInt();
            $restes[$id] = $produit->mod($somme)->toInt();
        }
        $ordre = array_keys($poids);
        usort($ordre, fn (int $a, int $b) => [$restes[$b], $poids[$b], $a] <=> [$restes[$a], $poids[$a], $b]);
        foreach (array_slice($ordre, 0, $total - array_sum($parts)) as $id) {
            $parts[$id]++;
        }

        return $parts;
    }
}
