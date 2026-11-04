@extends('layouts.app')

@section('titre', 'Journal '.$devise->value)

@section('contenu')
    <h1 class="h3 mb-3">Comptabilité</h1>
    @include('comptabilite._onglets')
    @include('comptabilite._periode')

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 small">
                <thead><tr><th>Date</th><th>Opération</th><th>Compte</th><th>SYSCOHADA</th><th class="text-end">Débit</th><th class="text-end">Crédit</th></tr></thead>
                <tbody>
                    @forelse ($operations as $operation)
                        @foreach ($operation->ecritures as $i => $ecriture)
                            <tr @class(['border-top' => $i === 0])>
                                <td>{{ $i === 0 ? $operation->date->format('d/m/Y') : '' }}</td>
                                <td>{{ $i === 0 ? "n° {$operation->id} · {$operation->type}" : '' }}@if ($i === 0 && $operation->motif)<div class="text-body-secondary">{{ $operation->motif }}</div>@endif</td>
                                <td>{{ $ecriture->compte }}</td>
                                <td>{{ $ecriture->syscohada }}</td>
                                <td class="text-end">{{ $ecriture->debit->estZero() ? '' : $ecriture->debit->formate() }}</td>
                                <td class="text-end">{{ $ecriture->credit->estZero() ? '' : $ecriture->credit->formate() }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Aucune opération.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $operations->links() }}</div>
@endsection
