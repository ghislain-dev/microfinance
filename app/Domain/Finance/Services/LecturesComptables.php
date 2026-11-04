<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\Ecriture;
use App\Domain\Finance\Models\Operation;
use App\Support\Montant\Devise;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lectures du grand livre pour les états comptables (ADR 023), par date de valeur et par devise.
 * Seul point d'accès aux opérations et écritures hors du moteur ; aucune écriture ici.
 */
final class LecturesComptables
{
    /**
     * Totaux débit / crédit par compte, sur les opérations de date de valeur comprise entre $du et $au (incluses,
     * l'une ou l'autre facultative).
     *
     * @return array<int, array{debit: int, credit: int}> compte_id => totaux en centimes
     */
    public function totauxParCompte(Devise $devise, ?CarbonImmutable $du, ?CarbonImmutable $au): array
    {
        return Ecriture::query()
            ->where('ecritures.devise', $devise->value)
            ->whereIn('operation_id', $this->operations($devise, $du, $au)->select('id'))
            ->groupBy('compte_id')
            ->selectRaw('compte_id, SUM(debit) AS total_debit, SUM(credit) AS total_credit')
            ->get()
            ->mapWithKeys(fn (Ecriture $e) => [(int) $e->getAttribute('compte_id') => [
                'debit' => (int) $e->getAttribute('total_debit'), 'credit' => (int) $e->getAttribute('total_credit'),
            ]])->all();
    }

    /**
     * Écritures des comptes donnés, dans l'ordre (date de valeur, opération).
     *
     * @param  list<int>  $comptes
     * @return list<array{date: CarbonImmutable, operation_id: int, type: string, motif: string|null, compte_id: int, debit: int, credit: int}>
     */
    public function mouvements(array $comptes, Devise $devise, CarbonImmutable $du, CarbonImmutable $au): array
    {
        $operations = $this->operations($devise, $du, $au)->get(['id', 'type', 'motif', 'date_valeur'])->keyBy('id');

        return Ecriture::query()->whereIn('compte_id', $comptes)->whereIn('operation_id', $operations->keys())
            ->orderBy('operation_id')->orderBy('id')->get()
            ->map(function (Ecriture $e) use ($operations) {
                $operation = $operations[$e->getAttribute('operation_id')];

                return [
                    'date' => CarbonImmutable::parse($operation->getAttribute('date_valeur')),
                    'operation_id' => (int) $operation->id,
                    'type' => (string) $operation->getAttribute('type'),
                    'motif' => is_string($motif = $operation->getAttribute('motif')) ? $motif : null,
                    'compte_id' => (int) $e->getAttribute('compte_id'),
                    'debit' => (int) $e->getAttribute('debit'),
                    'credit' => (int) $e->getAttribute('credit'),
                ];
            })->sortBy(fn (array $l) => [$l['date']->toDateString(), $l['operation_id']])->values()->all();
    }

    /**
     * Journal : opérations de la période et leurs écritures, paginé.
     *
     * @return LengthAwarePaginator<int, array{id: int, date: CarbonImmutable, type: string, motif: string|null, ecritures: list<array{compte_id: int, debit: int, credit: int}>}>
     */
    public function journal(Devise $devise, CarbonImmutable $du, CarbonImmutable $au, int $parPage = 50, ?int $numeroPage = null): LengthAwarePaginator
    {
        // Sans numéro : page de la requête (écran) ; avec : parcours complet (export).
        $page = $this->operations($devise, $du, $au)->with('ecritures')->orderBy('date_valeur')->orderBy('id')->paginate($parPage, ['*'], 'page', $numeroPage);

        return $page->through(fn (Operation $o) => [
            'id' => $o->id,
            'date' => CarbonImmutable::parse($o->getAttribute('date_valeur')),
            'type' => (string) $o->getAttribute('type'),
            'motif' => is_string($motif = $o->getAttribute('motif')) ? $motif : null,
            'ecritures' => array_values($o->ecritures->map(fn (Ecriture $e) => [
                'compte_id' => (int) $e->getAttribute('compte_id'), 'debit' => (int) $e->getAttribute('debit'), 'credit' => (int) $e->getAttribute('credit'),
            ])->all()),
        ]);
    }

    /**
     * @return Builder<Operation>
     */
    private function operations(Devise $devise, ?CarbonImmutable $du, ?CarbonImmutable $au): Builder
    {
        return Operation::query()->where('devise', $devise->value)
            ->when($du !== null, fn ($q) => $q->whereDate('date_valeur', '>=', $du?->toDateString()))
            ->when($au !== null, fn ($q) => $q->whereDate('date_valeur', '<=', $au?->toDateString()));
    }
}
