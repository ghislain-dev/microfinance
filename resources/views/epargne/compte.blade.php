@extends('layouts.app')

@section('titre', $compte->numero)

@section('contenu')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $compte->numero }}</h1>
            <p class="text-body-secondary mb-0">
                {{ $compte->type->libelle }} ·
                <a href="{{ route('membres.show', $compte->membre) }}">{{ $compte->membre->nomComplet() }}</a>
                @if ($compte->estBloque()) · <span class="badge text-bg-warning">Bloqué jusqu’au {{ $compte->bloque_jusqu_au?->format('d/m/Y') }}</span> @endif
            </p>
        </div>
        <div class="text-end">
            <div class="text-body-secondary small">Solde</div>
            <div class="fs-3">{{ $solde->formate() }}</div>
        </div>
    </div>

    @if (session('statut'))
        <div class="alert alert-info" role="status">{{ session('statut') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @foreach ($aPayer as $demande)
        @can('operer', $compte)
            <form method="POST" action="{{ route('demandes.payer', $demande) }}" class="alert alert-success d-flex justify-content-between align-items-center">
                @csrf
                <input type="hidden" name="cle" value="{{ $cle }}">
                <span>Retrait de {{ $demande->montant->formate() }} validé (demande n° {{ $demande->id }}).</span>
                <button type="submit" class="btn btn-success btn-sm">Payer</button>
            </form>
        @endcan
    @endforeach

    <div class="row g-3 mb-4">
        @can('operer', $compte)
            <div class="col-md-4">
                @include('epargne._operation', ['action' => route('comptes-epargne.depots', $compte), 'titre' => 'Déposer', 'bouton' => 'primary', 'id' => 'depot', 'devise' => $compte->type->devise->value])
            </div>
            <div class="col-md-4">
                @include('epargne._operation', ['action' => route('comptes-epargne.retraits', $compte), 'titre' => 'Retirer', 'bouton' => 'outline-primary', 'id' => 'retrait', 'devise' => $compte->type->devise->value])
            </div>
        @endcan
        @can('virer', [App\Domain\Epargne\Models\CompteEpargne::class, $compte, $compte])
            <div class="col-md-4">
                @include('epargne._operation', ['action' => route('comptes-epargne.virements', $compte), 'titre' => 'Virer', 'bouton' => 'outline-secondary', 'id' => 'virement', 'devise' => $compte->type->devise->value, 'destination' => true])
            </div>
        @endcan
    </div>

    <div class="card">
        <div class="card-header">Mouvements</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Date</th><th>Opération</th><th class="text-end">Montant</th><th class="text-end">Frais</th></tr></thead>
                <tbody>
                    @forelse ($mouvements as $mouvement)
                        <tr>
                            <td>{{ $mouvement->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $mouvement->libelleType() }}</td>
                            <td class="text-end">{{ $mouvement->montant->formate() }}</td>
                            <td class="text-end">{{ $mouvement->frais->estZero() ? '' : $mouvement->frais->formate() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">Aucun mouvement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $mouvements->links() }}</div>
@endsection
