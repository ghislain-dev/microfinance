@extends('layouts.app')

@section('titre', 'Taux de change')

@section('contenu')
    <h1 class="h3 mb-4">Taux de change <span class="text-body-secondary fs-6">CDF pour 1 USD</span></h1>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h6 text-body-secondary">Taux du jour ({{ today()->format('d/m/Y') }})</h2>
            @if ($duJour)
                <div class="row">
                    <div class="col-md-4"><div class="small text-body-secondary">Achat (USD → CDF)</div><div class="fs-4">{{ $duJour->achat()->formate() }}</div></div>
                    <div class="col-md-4"><div class="small text-body-secondary">Vente (CDF → USD)</div><div class="fs-4">{{ $duJour->vente()->formate() }}</div></div>
                    <div class="col-md-4"><div class="small text-body-secondary">Moyen (référence de la marge)</div><div class="fs-4">{{ $moyen?->formate() }}</div></div>
                </div>
            @else
                <p class="mb-0">Aucun taux saisi aujourd’hui : aucune conversion n’est possible.</p>
            @endif
        </div>
    </div>

    @can('create', App\Domain\Change\Models\TauxChange::class)
        <form method="POST" action="{{ route('change.index') }}" class="card card-body mb-4">
            @csrf
            <h2 class="h6">{{ $duJour ? 'Corriger le taux du jour' : 'Saisir le taux du jour' }}</h2>
            <div class="row g-2 align-items-end">
                @foreach (['achat' => 'Achat', 'vente' => 'Vente'] as $champ => $libelle)
                    <div class="col-md-4">
                        <label for="{{ $champ }}" class="form-label">{{ $libelle }}</label>
                        <input id="{{ $champ }}" name="{{ $champ }}" value="{{ old($champ) }}" inputmode="decimal" required placeholder="2850,00"
                               @class(['form-control', 'is-invalid' => $errors->has($champ)])>
                        @error($champ) <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endforeach
                <div class="col-auto"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
            </div>
        </form>
    @endcan

    <div class="card">
        <div class="card-header">Historique</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Date</th><th>Saisi le</th><th class="text-end">Achat</th><th class="text-end">Vente</th><th>Par</th></tr></thead>
                <tbody>
                    @forelse ($historique as $taux)
                        <tr>
                            <td>{{ $taux->date_application->format('d/m/Y') }}</td>
                            <td>{{ App\Support\Temps\Horloge::affiche($taux->created_at) }}</td>
                            <td class="text-end">{{ $taux->achat()->formate() }}</td>
                            <td class="text-end">{{ $taux->vente()->formate() }}</td>
                            <td>{{ $taux->saisiPar?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">Aucun taux.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $historique->links() }}</div>
@endsection
