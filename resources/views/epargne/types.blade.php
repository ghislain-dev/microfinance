@extends('layouts.app')

@section('titre', 'Produits d’épargne')

@section('contenu')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Produits d’épargne</h1>
        @can('create', App\Domain\Epargne\Models\TypeCompteEpargne::class)
            <a href="{{ route('epargne.types.create') }}" class="btn btn-primary">Nouveau produit</a>
        @endcan
    </div>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr><th>Code</th><th>Libellé</th><th>Devise</th><th class="text-end">Solde min.</th><th class="text-end">Dépôt min.</th>
                        <th class="text-end">Frais de retrait</th><th class="text-end">Intérêt annuel</th><th>Blocage</th><th>État</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        <tr>
                            <td>{{ $type->code }}</td>
                            <td>{{ $type->libelle }}</td>
                            <td>{{ $type->devise->value }}</td>
                            <td class="text-end">{{ $type->solde_minimum->formate() }}</td>
                            <td class="text-end">{{ $type->depot_minimum->formate() }}</td>
                            <td class="text-end">{{ $type->frais_retrait->formate() }}</td>
                            <td class="text-end">{{ $type->tauxInteretFormate() }}</td>
                            <td>{{ $type->duree_blocage_mois ? $type->duree_blocage_mois.' mois' : '—' }}</td>
                            <td>{{ $type->actif ? 'Proposé' : 'Retiré' }}</td>
                            <td class="text-end"><a href="{{ route('epargne.types.edit', $type) }}">Modifier</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-body-secondary py-4">Aucun produit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $types->links() }}</div>
@endsection
