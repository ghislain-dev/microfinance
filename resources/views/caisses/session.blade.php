@extends('layouts.app')

@section('titre', 'Session '.$session->caisse->code)

@section('contenu')
    <h1 class="h3 mb-1">Caisse {{ $session->caisse->code }} — {{ $session->caissier->name }}</h1>
    <p class="mb-4">
        <span @class(['badge', 'text-bg-success' => $session->statut === App\Domain\Caisse\StatutSession::Ouverte,
                      'text-bg-warning' => $session->statut->attendValidation(),
                      'text-bg-secondary' => $session->statut === App\Domain\Caisse\StatutSession::Fermee])>{{ $session->statut->libelle() }}</span>
        <span class="text-body-secondary ms-2">ouverte le {{ $session->ouverte_le->format('d/m/Y H:i') }}
            @if ($session->fermee_le) · fermée le {{ $session->fermee_le->format('d/m/Y H:i') }} @endif</span>
    </p>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif

    <div class="row g-4 mb-4">
        @foreach ($totaux as $moment => $parDevise)
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h6 text-body-secondary">Comptage {{ $moment === 'ouverture' ? 'd’ouverture' : 'de clôture' }}</h2>
                        @foreach ($parDevise as $total)
                            <p class="mb-1">{{ $total->formate() }}</p>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($session->ecarts->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header">Écarts constatés</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Comptage</th><th>Attendu</th><th>Compté</th><th>Écart</th></tr></thead>
                    <tbody>
                        @foreach ($session->ecarts as $ecart)
                            <tr>
                                <td>{{ $ecart->moment->value === 'ouverture' ? 'Ouverture' : 'Clôture' }}</td>
                                <td>{{ $ecart->theorique->formate() }}</td>
                                <td>{{ $ecart->compte->formate() }}</td>
                                <td @class(['text-danger' => $ecart->ecart->estNegatif()])>{{ $ecart->ecart->formate() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($session->statut->attendValidation())
        @can('validerEcart', $session)
            <form method="POST" action="{{ route('sessions-caisse.valider', $session) }}" class="card card-body mb-4">
                @csrf
                <label for="motif" class="form-label">Motif de la validation</label>
                <textarea id="motif" name="motif" rows="2" maxlength="500" required
                          @class(['form-control', 'is-invalid' => $errors->has('motif')])>{{ old('motif') }}</textarea>
                @error('motif') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <p class="form-text">Manquant : imputé au caissier. Excédent : enregistré en produit.</p>
                <div><button type="submit" class="btn btn-warning">Valider et comptabiliser l’écart</button></div>
            </form>
        @else
            <div class="alert alert-warning">En attente de la validation du gérant.</div>
        @endcan
    @endif

    @if ($session->statut === App\Domain\Caisse\StatutSession::Ouverte)
        @can('cloturer', $session)
            <form method="POST" action="{{ route('sessions-caisse.cloturer', $session) }}" class="card card-body">
                @csrf
                <h2 class="h5">Clôturer la session</h2>
                @include('caisses._billetage')
                <div class="mt-4"><button type="submit" class="btn btn-primary">Clôturer</button></div>
            </form>
        @endcan
    @endif
@endsection
