@extends('layouts.app')

@section('titre', $lot->libelleType().' '.$lot->periode)

@section('contenu')
    <h1 class="h3 mb-1">{{ $lot->libelleType() }} {{ $lot->devise->value }} — {{ $lot->periode }}</h1>
    <p class="text-body-secondary mb-4">
        Total {{ $lot->montant_total->formate() }} · solde de référence au {{ $lot->date_reference->format('d/m/Y') }} ·
        <span class="badge text-bg-{{ $lot->statut === App\Domain\Epargne\Remuneration\StatutRemuneration::Brouillon ? 'warning' : 'secondary' }}">{{ $lot->statut->libelle() }}</span>
        @if ($lot->decidePar) · {{ $lot->decidePar->name }} : {{ $lot->motif_decision }} @endif
    </p>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif
    @error('motif') <div class="alert alert-danger" role="alert">{{ $message }}</div> @enderror

    @can('decider', $lot)
        <div class="row g-2 mb-4">
            @foreach (['valider' => ['Valider et verser', 'success'], 'rejeter' => ['Rejeter', 'outline-danger']] as $action => [$libelle, $couleur])
                <form method="POST" action="{{ route('epargne.remunerations.'.$action, $lot) }}" class="col-md-6 d-flex gap-2">
                    @csrf
                    <input name="motif" maxlength="500" required placeholder="Motif" class="form-control" aria-label="Motif">
                    <button type="submit" class="btn btn-{{ $couleur }}">{{ $libelle }}</button>
                </form>
            @endforeach
        </div>
    @elseif ($lot->statut === App\Domain\Epargne\Remuneration\StatutRemuneration::Brouillon)
        <div class="alert alert-warning">À faire valider par une autre personne (gérant ou comptable).</div>
    @endcan

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Compte</th><th class="text-end">Base</th><th class="text-end">Montant</th></tr></thead>
                <tbody>
                    @foreach ($lignes as $ligne)
                        <tr>
                            <td>{{ $ligne->compteEpargne->numero }}</td>
                            <td class="text-end">{{ $ligne->base->formate() }}</td>
                            <td class="text-end">{{ $ligne->montant->formate() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $lignes->links() }}</div>
@endsection
