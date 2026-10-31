@extends('layouts.app')

@section('titre', $membre->numero)

@section('contenu')
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $membre->nomComplet() }}</h1>
            <p class="text-body-secondary mb-0">
                {{ $membre->numero }} · {{ $membre->agence?->nom }}
                <span @class(['badge ms-2', 'text-bg-success' => $membre->statut === App\Domain\Membres\StatutMembre::Actif,
                              'text-bg-secondary' => $membre->statut !== App\Domain\Membres\StatutMembre::Actif])>{{ $membre->statut->libelle() }}</span>
            </p>
        </div>
        @can('update', $membre)
            <a href="{{ route('membres.edit', $membre) }}" class="btn btn-outline-primary">Modifier</a>
        @endcan
    </div>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Sexe</dt><dd class="col-sm-9">{{ $membre->sexe->libelle() }}</dd>
                <dt class="col-sm-3">Date de naissance</dt><dd class="col-sm-9">{{ $membre->date_naissance->format('d/m/Y') }}</dd>
                <dt class="col-sm-3">Téléphone</dt><dd class="col-sm-9">{{ $membre->telephone }}</dd>
                {{-- Numéro de pièce masqué : seuls les 4 derniers caractères sont affichés. --}}
                <dt class="col-sm-3">Pièce d’identité</dt><dd class="col-sm-9">{{ $membre->piece_type }} · ••••{{ mb_substr($membre->piece_numero, -4) }}</dd>
                <dt class="col-sm-3">Adresse</dt><dd class="col-sm-9">{{ $membre->adresse ?? '—' }}</dd>
                <dt class="col-sm-3">Inscrit le</dt><dd class="col-sm-9 mb-0">{{ $membre->created_at?->format('d/m/Y') }}</dd>
            </dl>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Comptes d’épargne</div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Numéro</th><th>Produit</th><th class="text-end">Solde</th><th>Blocage</th></tr></thead>
                <tbody>
                    @forelse ($comptes as ['compte' => $compte, 'solde' => $solde])
                        <tr>
                            <td><a href="{{ route('comptes-epargne.show', $compte) }}">{{ $compte->numero }}</a></td>
                            <td>{{ $compte->type->libelle }}</td>
                            <td class="text-end">{{ $solde->formate() }}</td>
                            <td>
                                @if ($compte->bloque_jusqu_au)
                                    Bloqué jusqu’au {{ $compte->bloque_jusqu_au->format('d/m/Y') }}
                                @else
                                    <span class="text-body-secondary">Libre</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">Aucun compte.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @can('create', [App\Domain\Epargne\Models\CompteEpargne::class, $membre])
            <form method="POST" action="{{ route('membres.comptes-epargne.store', $membre) }}" class="card-footer">
                @csrf
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label for="type_compte_epargne_id" class="form-label">Ouvrir un compte</label>
                        <select id="type_compte_epargne_id" name="type_compte_epargne_id" required
                                @class(['form-select', 'is-invalid' => $errors->has('type_compte_epargne_id')])>
                            <option value=""></option>
                            @foreach ($typesProposes as $type)
                                <option value="{{ $type->id }}">{{ $type->code }} — {{ $type->libelle }} ({{ $type->devise->value }})</option>
                            @endforeach
                        </select>
                        @error('type_compte_epargne_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-auto"><button type="submit" class="btn btn-outline-primary">Ouvrir</button></div>
                </div>
            </form>
        @endcan
    </div>

    @can('changerStatut', $membre)
        <form method="POST" action="{{ route('membres.statut', $membre) }}" class="card card-body">
            @csrf
            @method('PATCH')
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="statut" class="form-label">Changer le statut</label>
                    <select id="statut" name="statut" @class(['form-select', 'is-invalid' => $errors->has('statut')])>
                        @foreach ($statuts as $statut)
                            <option value="{{ $statut->value }}" @selected($membre->statut === $statut)>{{ $statut->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('statut') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Appliquer</button>
                </div>
            </div>
        </form>
    @endcan
@endsection
