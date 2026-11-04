<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Services;

use App\Domain\Comptabilite\PlanSyscohada;
use App\Domain\Finance\Models\Compte;
use App\Domain\Finance\Services\LecturesComptables;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Journal, grand livre et balance par devise (ADR 023), regroupés selon la correspondance SYSCOHADA.
 * Tous les totaux sont calculés ici, en centimes entiers ; les vues ne font qu'afficher.
 */
final class EtatsComptables
{
    public function __construct(private readonly LecturesComptables $lectures, private readonly PlanSyscohada $plan) {}

    public function balance(Devise $devise, CarbonImmutable $du, CarbonImmutable $au): Balance
    {
        $avant = $this->lectures->totauxParCompte($devise, null, $du->subDay());
        $periode = $this->lectures->totauxParCompte($devise, $du, $au);
        $comptes = Compte::query()->whereIn('id', array_keys($avant + $periode))->get();

        $parRubrique = [];
        foreach ($comptes as $compte) {
            $rubrique = $this->plan->rubrique($compte);
            $a = $avant[$compte->id] ?? ['debit' => 0, 'credit' => 0];
            $p = $periode[$compte->id] ?? ['debit' => 0, 'credit' => 0];
            $ligne = $parRubrique[$rubrique] ?? ['ouverture' => 0, 'debit' => 0, 'credit' => 0];
            $parRubrique[$rubrique] = [
                'ouverture' => $ligne['ouverture'] + $a['debit'] - $a['credit'],
                'debit' => $ligne['debit'] + $p['debit'],
                'credit' => $ligne['credit'] + $p['credit'],
            ];
        }

        $lignes = [];
        foreach ($parRubrique as $rubrique => $t) {
            [$numero, $libelle] = $this->plan->correspondance($rubrique);
            $lignes[] = new LigneBalance($rubrique, $numero, $libelle, new Montant($t['ouverture'], $devise), new Montant($t['debit'], $devise),
                new Montant($t['credit'], $devise), new Montant($t['ouverture'] + $t['debit'] - $t['credit'], $devise));
        }
        usort($lignes, fn (LigneBalance $a, LigneBalance $b) => strcmp($a->syscohada, $b->syscohada));

        return new Balance($devise, $lignes,
            new Montant(array_sum(array_column($parRubrique, 'debit')), $devise),
            new Montant(array_sum(array_column($parRubrique, 'credit')), $devise));
    }

    public function grandLivre(string $rubrique, Devise $devise, CarbonImmutable $du, CarbonImmutable $au): GrandLivre
    {
        $comptes = Compte::query()->where('devise', $devise->value)->get()
            ->filter(fn (Compte $c) => $this->plan->rubrique($c) === $rubrique)->keyBy('id');
        $ids = $comptes->keys()->map(fn ($id) => (int) $id)->values()->all();

        $avant = array_intersect_key($this->lectures->totauxParCompte($devise, null, $du->subDay()), array_flip($ids));
        $solde = array_sum(array_column($avant, 'debit')) - array_sum(array_column($avant, 'credit'));
        $ouverture = new Montant($solde, $devise);

        $lignes = [];
        foreach ($this->lectures->mouvements($ids, $devise, $du, $au) as $m) {
            $solde += $m['debit'] - $m['credit'];
            $lignes[] = new LigneGrandLivre($m['date'], $m['operation_id'], $m['type'], $m['motif'], (string) $comptes[$m['compte_id']]?->numero,
                new Montant($m['debit'], $devise), new Montant($m['credit'], $devise), new Montant($solde, $devise));
        }
        [$numero, $libelle] = $this->plan->correspondance($rubrique);

        return new GrandLivre($rubrique, $numero, $libelle, $ouverture, $lignes, new Montant($solde, $devise));
    }

    /**
     * @return LengthAwarePaginator<int, OperationJournal>
     */
    public function journal(Devise $devise, CarbonImmutable $du, CarbonImmutable $au, int $parPage = 50): LengthAwarePaginator
    {
        $page = $this->lectures->journal($devise, $du, $au, $parPage);
        $ids = collect($page->items())->flatMap(fn (array $o) => array_column($o['ecritures'], 'compte_id'))->unique()->values();
        $comptes = Compte::query()->whereIn('id', $ids)->get()->keyBy('id');

        return $page->through(fn (array $o) => new OperationJournal($o['id'], $o['date'], $o['type'], $o['motif'],
            array_map(fn (array $e) => new EcritureJournal(
                (string) $comptes[$e['compte_id']]?->numero,
                isset($comptes[$e['compte_id']]) ? $this->plan->numero($comptes[$e['compte_id']]) : '?',
                new Montant($e['debit'], $devise), new Montant($e['credit'], $devise),
            ), $o['ecritures'])));
    }
}
