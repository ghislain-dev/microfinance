@extends('layouts.app')

@section('titre', 'Versements des collecteurs')

@section('contenu')
    <h1 class="h3 mb-4">Versements des collecteurs</h1>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif
    @if ($errors->any() && ! $errors->has('billetage'))
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header">Espèces détenues par les collecteurs</div>
        <ul class="list-group list-group-flush">
            @forelse ($collecteurs as $collecteur)
                <li class="list-group-item">{{ $collecteur->name }} :
                    @forelse ($especes[$collecteur->id] as $devise => $montant) {{ $montant->formate() }}@if (! $loop->last) · @endif @empty aucune caisse @endforelse
                </li>
            @empty
                <li class="list-group-item text-body-secondary">Aucun collecteur.</li>
            @endforelse
        </ul>
    </div>

    @can('recevoir', App\Domain\Collecte\Models\VersementCollecte::class)
        <form method="POST" action="{{ route('versements.store') }}" class="card card-body mb-4">
            @csrf
            <h2 class="h6">Recevoir un versement</h2>
            <p class="small text-body-secondary">Comptez les billets remis par le collecteur ; un écart avec ses espèces théoriques attend la validation du gérant.</p>
            <div class="col-md-4 mb-3">
                <label for="collecteur_id" class="form-label">Collecteur</label>
                <select id="collecteur_id" name="collecteur_id" required class="form-select">
                    @foreach ($collecteurs as $collecteur)
                        <option value="{{ $collecteur->id }}" @selected((int) old('collecteur_id') === $collecteur->id)>{{ $collecteur->name }}</option>
                    @endforeach
                </select>
            </div>
            @include('caisses._billetage')
            <div class="mt-3"><button type="submit" class="btn btn-primary">Enregistrer le versement</button></div>
        </form>
    @endcan

    <div class="card">
        <div class="card-header">Versements</div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle small">
                <thead><tr><th>Date</th><th>Collecteur</th><th class="text-end">Théorique</th><th class="text-end">Compté</th><th class="text-end">Écart</th><th>Statut</th></tr></thead>
                <tbody>
                    @forelse ($versements as $v)
                        <tr>
                            <td>{{ App\Support\Temps\Horloge::affiche($v->created_at) }}</td>
                            <td>{{ $v->collecteur->name }}</td>
                            <td class="text-end">{{ $v->theorique->formate() }}</td>
                            <td class="text-end">{{ $v->compte->formate() }}</td>
                            <td class="text-end">{{ $v->ecart->formate() }}</td>
                            <td>
                                @if ($v->statut === 'valide') <span class="badge text-bg-success">Validé</span>
                                @else
                                    <span class="badge text-bg-warning">Écart à justifier</span>
                                    @can('valider', $v)
                                        <form method="POST" action="{{ route('versements.valider', $v) }}" class="d-flex gap-2 mt-1">
                                            @csrf
                                            <input name="motif" required maxlength="500" placeholder="Motif" aria-label="Motif" class="form-control form-control-sm">
                                            <button type="submit" class="btn btn-sm btn-success">Valider</button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">Aucun versement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $versements->links() }}</div>
@endsection
