@extends('layouts.app')

@section('titre', 'Grand livre '.$livre->syscohada)

@section('contenu')
    <h1 class="h3 mb-3">Grand livre — {{ $livre->syscohada }} {{ $livre->libelle }}</h1>
    @include('comptabilite._onglets')
    @include('comptabilite._periode', ['caches' => ['rubrique' => $livre->rubrique]])

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle small">
                <thead><tr><th>Date</th><th>Opération</th><th>Compte</th><th>Motif</th><th class="text-end">Débit</th><th class="text-end">Crédit</th><th class="text-end">Solde</th></tr></thead>
                <tbody>
                    <tr class="table-light"><td colspan="6">Solde d’ouverture</td><td class="text-end">{{ $livre->ouverture->formate() }}</td></tr>
                    @foreach ($livre->lignes as $ligne)
                        <tr>
                            <td>{{ $ligne->date->format('d/m/Y') }}</td>
                            <td>n° {{ $ligne->operationId }} · {{ $ligne->type }}</td>
                            <td>{{ $ligne->compte }}</td>
                            <td>{{ $ligne->motif }}</td>
                            <td class="text-end">{{ $ligne->debit->estZero() ? '' : $ligne->debit->formate() }}</td>
                            <td class="text-end">{{ $ligne->credit->estZero() ? '' : $ligne->credit->formate() }}</td>
                            <td class="text-end">{{ $ligne->solde->formate() }}</td>
                        </tr>
                    @endforeach
                    <tr class="table-light fw-semibold"><td colspan="6">Solde de clôture</td><td class="text-end">{{ $livre->cloture->formate() }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
