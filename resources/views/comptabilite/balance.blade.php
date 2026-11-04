@extends('layouts.app')

@section('titre', 'Balance '.$balance->devise->value)

@section('contenu')
    <h1 class="h3 mb-3">Comptabilité</h1>
    @include('comptabilite._onglets')
    @include('comptabilite._periode', ['devise' => $balance->devise])

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle small">
                <thead>
                    <tr><th>Compte</th><th>Libellé</th><th class="text-end">Solde d’ouverture</th><th class="text-end">Débit</th><th class="text-end">Crédit</th><th class="text-end">Solde de clôture</th></tr>
                </thead>
                <tbody>
                    @forelse ($balance->lignes as $ligne)
                        <tr>
                            <td><a href="{{ route('comptabilite.grand-livre', ['rubrique' => $ligne->rubrique, 'devise' => $balance->devise->value, 'du' => $du->toDateString(), 'au' => $au->toDateString()]) }}">{{ $ligne->syscohada }}</a></td>
                            <td>{{ $ligne->libelle }}</td>
                            <td class="text-end">{{ $ligne->ouverture->formate() }}</td>
                            <td class="text-end">{{ $ligne->debit->formate() }}</td>
                            <td class="text-end">{{ $ligne->credit->formate() }}</td>
                            <td class="text-end">{{ $ligne->cloture->formate() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Aucune écriture.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="fw-semibold"><td colspan="3">Totaux de la période</td><td class="text-end">{{ $balance->totalDebit->formate() }}</td><td class="text-end">{{ $balance->totalCredit->formate() }}</td><td></td></tr>
                </tfoot>
            </table>
        </div>
    </div>
    <p class="small text-body-secondary mt-2">Soldes positifs : débiteurs ; négatifs : créditeurs. Dates de valeur.</p>
@endsection
