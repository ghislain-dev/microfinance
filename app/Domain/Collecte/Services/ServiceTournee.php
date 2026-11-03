<?php

declare(strict_types=1);

namespace App\Domain\Collecte\Services;

use App\Domain\Collecte\Models\ParametresCollecte;
use App\Domain\Collecte\Models\PlafondCollecte;
use App\Domain\Collecte\Models\Tournee;
use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\Models\Pret;
use App\Domain\Credit\StatutPret;
use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Epargne\Models\SeuilValidation;
use App\Domain\Epargne\Services\Blocages;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Models\Membre;
use App\Domain\Tontines\Models\Cotisation;
use App\Domain\Tontines\Models\Cycle;
use App\Domain\Tontines\Models\Seance;
use App\Domain\Tontines\Services\Dettes;
use App\Domain\Tontines\StatutCycle;
use App\Domain\Tontines\StatutSeance;
use App\Domain\Tontines\TypeGroupe;
use App\Http\Resources\MontantJson;
use App\Models\User;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ce que le téléphone du collecteur garde hors-ligne : membres de sa tournée avec soldes, cotisations
 * attendues et prêts, paramètres de collecte et espèces détenues. Les soldes sont ceux du serveur.
 */
final class ServiceTournee
{
    public function __construct(
        private readonly MoteurFinancier $moteur,
        private readonly Blocages $blocages,
        private readonly Dettes $dettes,
        private readonly CaisseCollecteur $caisses,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function pour(User $collecteur, ?CarbonImmutable $depuis): array
    {
        $serveurLe = now()->toIso8601String();
        $actives = Tournee::query()->where('collecteur_id', $collecteur->id)->whereNull('retire_le');
        $membreIds = $actives->pluck('membre_id')->map(fn ($id) => (int) $id)->all();
        if ($depuis !== null) {
            $membreIds = $this->modifiesDepuis($collecteur, $membreIds, $depuis);
        }

        return [
            'serveur_le' => $serveurLe,
            'parametres' => $this->parametres(),
            'especes_detenues' => $this->especes($collecteur),
            'membres' => Membre::query()->whereIn('id', $membreIds)->orderBy('numero')->get()->map(fn (Membre $m) => $this->membre($m))->values()->all(),
            'membres_retires' => $depuis === null ? [] : Tournee::query()->where('collecteur_id', $collecteur->id)
                ->where('retire_le', '>', $depuis)->pluck('membre_id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function modifiesDepuis(User $collecteur, array $ids, CarbonImmutable $depuis): array
    {
        $comptes = CompteEpargne::query()->whereIn('membre_id', $ids)->select('id');
        $prets = Pret::query()->whereIn('membre_id', $ids)->select('id');
        $modifies = collect()
            ->merge(Tournee::query()->where('collecteur_id', $collecteur->id)->whereNull('retire_le')->where('updated_at', '>', $depuis)->pluck('membre_id'))
            ->merge(CompteEpargne::query()->whereIn('membre_id', $ids)
                ->whereIn('id', DB::table('mouvements_epargne')->select('compte_epargne_id')->whereIn('compte_epargne_id', $comptes)->where('created_at', '>', $depuis))
                ->pluck('membre_id'))
            ->merge(Pret::query()->whereIn('membre_id', $ids)->where('updated_at', '>', $depuis)->pluck('membre_id'))
            ->merge(Pret::query()->whereIn('id', EcheancePret::query()->whereIn('pret_id', $prets)->where('updated_at', '>', $depuis)->select('pret_id'))->pluck('membre_id'))
            ->merge(Cotisation::query()->whereIn('membre_id', $ids)->where('created_at', '>', $depuis)->pluck('membre_id'));

        return $modifies->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parametres(): ?array
    {
        $parametres = ParametresCollecte::query()->first();
        if ($parametres === null) {
            return null; // sans paramètres, l'application ne permet aucune saisie
        }
        $seuils = SeuilValidation::query()->get()->keyBy(fn (SeuilValidation $s) => $s->devise->value);

        return [
            'delai_max_heures' => $parametres->delai_max_heures,
            'plafonds' => PlafondCollecte::query()->get()->mapWithKeys(fn (PlafondCollecte $p) => [$p->devise->value => [
                'plafond_retrait' => MontantJson::de($p->plafond_retrait),
                'plafond_especes' => MontantJson::de($p->plafond_especes),
                'seuil_validation' => MontantJson::de($seuils->get($p->devise->value)?->montant),
            ]])->all(),
        ];
    }

    /**
     * @return array<string, array{centimes: int, devise: string, texte: string}|null>
     */
    private function especes(User $collecteur): array
    {
        $caisse = $this->caisses->de($collecteur);

        return collect(Devise::cases())->mapWithKeys(fn (Devise $d) => [$d->value => MontantJson::de(
            $caisse === null ? Montant::zero($d) : $this->moteur->soldeReel($caisse->compte($d)),
        )])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function membre(Membre $membre): array
    {
        return [
            'id' => $membre->id,
            'numero' => $membre->numero,
            'nom' => $membre->nomComplet(),
            'telephone' => $membre->telephone,
            'statut' => $membre->statut->value,
            'comptes' => CompteEpargne::query()->with(['type', 'compte'])->where('membre_id', $membre->id)->orderBy('id')->get()
                ->map(function (CompteEpargne $c) {
                    $solde = $this->moteur->soldeReel($c->compte);

                    return ['id' => $c->id, 'numero' => $c->numero, 'devise' => $c->type->devise->value, 'bloque' => $c->estBloque(),
                        'solde' => MontantJson::de($solde), 'disponible' => MontantJson::de($this->blocages->disponible($c, $solde))];
                })->values()->all(),
            'tontines' => $this->tontines($membre),
            'prets' => Pret::query()->with('echeances')->where('membre_id', $membre->id)->where('statut', StatutPret::EnCours->value)->get()
                ->map(fn (Pret $p) => $this->pret($p))->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tontines(Membre $membre): array
    {
        /** @var Collection<int, Cycle> $cycles */
        $cycles = Cycle::query()->with('groupe')->where('statut', StatutCycle::EnCours->value)
            ->whereHas('participants', fn ($q) => $q->whereKey($membre->id))->get();

        return $cycles->map(function (Cycle $cycle) use ($membre) {
            $seance = Seance::query()->where('cycle_id', $cycle->id)->where('statut', StatutSeance::Prevue->value)->orderBy('rang')->first();
            $avec = $cycle->groupe->type === TypeGroupe::Avec;

            return [
                'cycle_id' => $cycle->id,
                'groupe' => $cycle->groupe->nom,
                'type' => $cycle->groupe->type->value,
                'devise' => $cycle->devise->value,
                'prochaine_seance' => $seance === null ? null : [
                    'id' => $seance->id, 'rang' => $seance->getAttribute('rang'), 'date' => $seance->date_prevue->toDateString(),
                    'deja_paye' => Cotisation::query()->where('seance_id', $seance->id)->where('membre_id', $membre->id)
                        ->whereIn('type', ['cotisation', 'parts'])->exists(),
                ],
                'cotisation' => $avec ? null : MontantJson::de($cycle->montant_cotisation),
                'prix_part' => $avec ? MontantJson::de($cycle->montant_cotisation) : null,
                'parts_min' => $avec ? $cycle->getAttribute('parts_min') : null,
                'parts_max' => $avec ? $cycle->getAttribute('parts_max') : null,
                'fonds_social' => MontantJson::de($cycle->cotisation_fonds_social),
                'dette' => MontantJson::de($this->dettes->du($cycle, $membre)),
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function pret(Pret $pret): array
    {
        $zero = Montant::zero($pret->devise);
        $aujourdhui = CarbonImmutable::today();

        return [
            'id' => $pret->id,
            'numero' => $pret->numero,
            'devise' => $pret->devise->value,
            'reste_du' => MontantJson::de($pret->echeances->reduce(fn (Montant $t, EcheancePret $e) => $t->plus($e->resteDu()), $zero)),
            'du_a_ce_jour' => MontantJson::de($pret->echeances->filter(fn (EcheancePret $e) => $e->date_echeance->lte($aujourdhui))
                ->reduce(fn (Montant $t, EcheancePret $e) => $t->plus($e->resteDu()), $zero)),
        ];
    }
}
