@extends('layouts.app')

@section('titre', 'Membres')

@section('contenu')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Membres</h1>
        @can('create', App\Domain\Membres\Models\Membre::class)
            <a href="{{ route('membres.create') }}" class="btn btn-primary">Inscrire un membre</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('membres.index') }}" class="mb-3" role="search">
        <div class="input-group">
            <input type="search" name="q" value="{{ $recherche }}" class="form-control"
                   placeholder="Nom, numéro (BUK-000045) ou téléphone" aria-label="Rechercher un membre">
            <button type="submit" class="btn btn-outline-secondary">Rechercher</button>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr><th>Numéro</th><th>Nom</th><th>Téléphone</th><th>Agence</th><th>Statut</th></tr>
                </thead>
                <tbody>
                    @forelse ($membres as $membre)
                        <tr>
                            <td><a href="{{ route('membres.show', $membre) }}">{{ $membre->numero }}</a></td>
                            <td>{{ $membre->nomComplet() }}</td>
                            <td>{{ $membre->telephone }}</td>
                            <td>{{ $membre->agence?->code }}</td>
                            <td>{{ $membre->statut->libelle() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">Aucun membre.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $membres->links() }}</div>
@endsection
