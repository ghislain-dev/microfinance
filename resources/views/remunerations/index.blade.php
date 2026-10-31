@extends('layouts.app')

@section('titre', 'Intérêts et ristournes')

@section('contenu')
    <h1 class="h3 mb-4">Intérêts et ristournes</h1>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <form method="POST" action="{{ route('epargne.remunerations.interets') }}" class="card card-body h-100">
                @csrf
                <h2 class="h6">Calculer les intérêts d’un mois</h2>
                <p class="small text-body-secondary">Solde de fin de mois × taux du produit / 12, membres actifs. Calculé
                    automatiquement le 1er de chaque mois ; rien n’est versé avant validation.</p>
                <div class="row g-2 align-items-end">
                    <div class="col-5">
                        <label for="periode" class="form-label small">Mois</label>
                        <input id="periode" name="periode" type="month" value="{{ old('periode', $moisEcoule) }}" required
                               @class(['form-control', 'is-invalid' => $errors->has('periode')])>
                    </div>
                    <div class="col-3">
                        <label for="devise-interets" class="form-label small">Devise</label>
                        <select id="devise-interets" name="devise" class="form-select">
                            @foreach (App\Support\Montant\Devise::cases() as $devise)
                                <option value="{{ $devise->value }}">{{ $devise->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto"><button type="submit" class="btn btn-primary">Calculer</button></div>
                </div>
                @error('periode') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
            </form>
        </div>
        <div class="col-md-6">
            <form method="POST" action="{{ route('epargne.remunerations.ristournes') }}" class="card card-body h-100">
                @csrf
                <h2 class="h6">Nouvelle ristourne</h2>
                <p class="small text-body-secondary mb-1">Montant décidé par l’assemblée, réparti au prorata de l’épargne des
                    membres actifs à la date de référence.</p>
                <p class="small mb-2">Excédents à répartir :
                    @foreach ($excedents as $solde) <strong>{{ $solde->formate() }}</strong>@if (! $loop->last) · @endif @endforeach
                </p>
                <div class="row g-2 align-items-end">
                    <div class="col-4">
                        <label for="montant" class="form-label small">Montant</label>
                        <input id="montant" name="montant" value="{{ old('montant') }}" inputmode="decimal" required
                               @class(['form-control', 'is-invalid' => $errors->has('montant')])>
                    </div>
                    <div class="col-3">
                        <label for="devise-ristourne" class="form-label small">Devise</label>
                        <select id="devise-ristourne" name="devise" class="form-select">
                            @foreach (App\Support\Montant\Devise::cases() as $devise)
                                <option value="{{ $devise->value }}">{{ $devise->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-5">
                        <label for="date_reference" class="form-label small">Date de référence</label>
                        <input id="date_reference" name="date_reference" type="date" value="{{ old('date_reference') }}" required
                               @class(['form-control', 'is-invalid' => $errors->has('date_reference')])>
                    </div>
                </div>
                @error('montant') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                @error('date_reference') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                <div class="mt-3"><button type="submit" class="btn btn-outline-primary">Répartir</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Lot</th><th>Période</th><th class="text-end">Total</th><th>Statut</th><th>Calculé par</th><th>Décidé par</th></tr></thead>
                <tbody>
                    @forelse ($lots as $lot)
                        <tr>
                            <td><a href="{{ route('epargne.remunerations.show', $lot) }}">{{ $lot->libelleType() }} {{ $lot->devise->value }}</a></td>
                            <td>{{ $lot->periode }}</td>
                            <td class="text-end">{{ $lot->montant_total->formate() }}</td>
                            <td>{{ $lot->statut->libelle() }}</td>
                            <td>{{ $lot->creePar?->name ?? 'Calcul automatique' }}</td>
                            <td>{{ $lot->decidePar?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Aucun lot.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $lots->links() }}</div>
@endsection
