@extends('layouts.app')

@section('titre', 'Caisses')

@section('contenu')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Caisses</h1>
        @can('create', App\Domain\Caisse\Models\Caisse::class)
            <a href="{{ route('caisses.create') }}" class="btn btn-primary">Créer une caisse</a>
        @endcan
    </div>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif

    @if ($aValider->isNotEmpty())
        <div class="card border-warning mb-4">
            <div class="card-header">Sessions à valider</div>
            <ul class="list-group list-group-flush">
                @foreach ($aValider as $session)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $session->caisse->code }} — {{ $session->caissier->name }} · {{ $session->statut->libelle() }}</span>
                        <a href="{{ route('sessions-caisse.show', $session) }}">Voir</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Code</th><th>Libellé</th><th>Agence</th><th>Session en cours</th><th></th></tr></thead>
                <tbody>
                    @forelse ($caisses as $caisse)
                        <tr>
                            <td>{{ $caisse->code }}</td>
                            <td>{{ $caisse->libelle }}</td>
                            <td>{{ $caisse->agence?->code }}</td>
                            <td>
                                @if ($caisse->sessionEnCours)
                                    <a href="{{ route('sessions-caisse.show', $caisse->sessionEnCours) }}">
                                        {{ $caisse->sessionEnCours->caissier?->name }} · {{ $caisse->sessionEnCours->statut->libelle() }}
                                    </a>
                                @else
                                    <span class="text-body-secondary">Fermée</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if (! $caisse->sessionEnCours)
                                    @can('operer', $caisse)
                                        <a href="{{ route('caisses.ouvrir', $caisse) }}" class="btn btn-sm btn-outline-primary">Ouvrir</a>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">Aucune caisse.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $caisses->links() }}</div>
@endsection
