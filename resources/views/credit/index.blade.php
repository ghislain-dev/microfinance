@extends('layouts.app')

@section('titre', 'Crédit')

@section('contenu')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Crédit</h1>
        @can('create', App\Domain\Credit\Models\Pret::class)
            <a href="{{ route('credit.demandes.create') }}" class="btn btn-primary">Nouvelle demande</a>
        @endcan
    </div>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @forelse ($par as [$par30, $par90])
            <div class="col-md-6">
                <div class="card card-body">
                    <div class="small text-body-secondary">Encours {{ $par30->encours->devise->value }} : {{ $par30->encours->formate() }}</div>
                    <div class="d-flex gap-4 mt-1">
                        <div>PAR 30 <strong>{{ App\Support\Montant\Pourcentage::formate($par30->tauxPb) }}</strong> <span class="small text-body-secondary">({{ $par30->aRisque->formate() }})</span></div>
                        <div>PAR 90 <strong>{{ App\Support\Montant\Pourcentage::formate($par90->tauxPb) }}</strong> <span class="small text-body-secondary">({{ $par90->aRisque->formate() }})</span></div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col"><div class="card card-body text-body-secondary">PAR 30 et PAR 90 : aucun prêt en cours.</div></div>
        @endforelse
    </div>

    <form method="GET" action="{{ route('credit.index') }}" class="row g-2 mb-3 align-items-end">
        <div class="col-md-3">
            <label for="statut" class="form-label small">Statut</label>
            <select id="statut" name="statut" class="form-select">
                <option value="">Tous</option>
                @foreach (App\Domain\Credit\StatutPret::cases() as $s)
                    <option value="{{ $s->value }}" @selected($statut === $s->value)>{{ $s->libelle() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Filtrer</button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Prêt</th><th>Membre</th><th class="text-end">Montant</th><th>Échéances</th><th>Statut</th><th>Demandé le</th></tr></thead>
                <tbody>
                    @forelse ($prets as $pret)
                        <tr>
                            <td><a href="{{ route('prets.show', $pret) }}">{{ $pret->numero }}</a></td>
                            <td>{{ $pret->membre->nomComplet() }}</td>
                            <td class="text-end">{{ $pret->montant->formate() }}</td>
                            <td>{{ $pret->nombre_echeances }} × {{ mb_strtolower($pret->periodicite->libelle()) }}</td>
                            <td><span class="badge text-bg-{{ $pret->statut->couleur() }}">{{ $pret->statut->libelle() }}</span></td>
                            <td>{{ $pret->created_at?->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Aucun prêt.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $prets->links() }}</div>
@endsection
