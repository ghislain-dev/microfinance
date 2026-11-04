@extends('layouts.app')

@section('titre', 'Demandes à valider')

@section('contenu')
    <h1 class="h3 mb-4">Demandes à valider</h1>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif
    @error('motif') <div class="alert alert-danger" role="alert">{{ $message }}</div> @enderror

    @forelse ($enAttente as $demande)
        <div class="card mb-3">
            <div class="card-body">
                <p class="mb-2">
                    <strong>{{ $demande->type === 'retrait' ? 'Retrait' : 'Virement' }} de {{ $demande->montant->formate() }}</strong>
                    sur {{ $demande->compteEpargne->numero }}
                    @if ($demande->destination) vers {{ $demande->destination->numero }} @endif
                    <span class="text-body-secondary">— demandé par {{ $demande->demandeur->name }}, le {{ App\Support\Temps\Horloge::affiche($demande->created_at) }}</span>
                </p>
                @can('decider', $demande)
                    <div class="row g-2">
                        @foreach (['approuver' => ['Valider', 'success'], 'rejeter' => ['Rejeter', 'outline-danger']] as $action => [$libelle, $couleur])
                            <form method="POST" action="{{ route('demandes.'.$action, $demande) }}" class="col-md-6 d-flex gap-2">
                                @csrf
                                <input name="motif" maxlength="500" required placeholder="Motif" class="form-control form-control-sm" aria-label="Motif">
                                <button type="submit" class="btn btn-{{ $couleur }} btn-sm">{{ $libelle }}</button>
                            </form>
                        @endforeach
                    </div>
                @else
                    <p class="text-body-secondary small mb-0">Vous ne pouvez pas décider de votre propre demande.</p>
                @endcan
            </div>
        </div>
    @empty
        <p class="text-body-secondary">Aucune demande en attente.</p>
    @endforelse

    <h2 class="h5 mt-5">Décisions récentes</h2>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>N°</th><th>Compte</th><th class="text-end">Montant</th><th>Statut</th><th>Par</th><th>Motif</th></tr></thead>
                <tbody>
                    @forelse ($recentes as $demande)
                        <tr>
                            <td>{{ $demande->id }}</td>
                            <td>{{ $demande->compteEpargne->numero }}</td>
                            <td class="text-end">{{ $demande->montant->formate() }}</td>
                            <td>{{ $demande->statut->libelle() }}</td>
                            <td>{{ $demande->decidePar?->name }}</td>
                            <td>{{ $demande->motif_decision }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">Aucune décision.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $recentes->links() }}</div>
@endsection
