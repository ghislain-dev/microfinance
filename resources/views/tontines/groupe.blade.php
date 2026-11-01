@extends('layouts.app')

@section('titre', $groupe->code)

@section('contenu')
    <h1 class="h3 mb-1">{{ $groupe->nom }} <span class="text-body-secondary fs-6">{{ $groupe->code }}</span></h1>
    <p class="text-body-secondary mb-4">{{ $groupe->type->libelle() }} · agence {{ $groupe->agence?->code }}</p>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">Membres ({{ $membres->count() }})</div>
                <ul class="list-group list-group-flush">
                    @forelse ($membres as $membre)
                        <li class="list-group-item"><a href="{{ route('membres.show', $membre) }}">{{ $membre->numero }}</a> — {{ $membre->nomComplet() }}</li>
                    @empty
                        <li class="list-group-item text-body-secondary">Aucun membre.</li>
                    @endforelse
                </ul>
                @can('update', $groupe)
                    <form method="POST" action="{{ route('tontines.inscrire', $groupe) }}" class="card-footer d-flex gap-2">
                        @csrf
                        <input name="numero" value="{{ old('numero') }}" required placeholder="Numéro de membre (BUK-000045)"
                               @class(['form-control', 'is-invalid' => $errors->has('numero')]) aria-label="Numéro de membre">
                        <button type="submit" class="btn btn-outline-primary">Inscrire</button>
                    </form>
                    @error('numero') <div class="text-danger small px-3 pb-2">{{ $message }}</div> @enderror
                @endcan
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">Cycles</div>
                <ul class="list-group list-group-flush">
                    @forelse ($cycles as $cycle)
                        <li class="list-group-item"><a href="{{ route('cycles.show', $cycle) }}">Cycle {{ $cycle->numero }}</a>
                            — {{ $cycle->statut->libelle() }}, {{ $cycle->montant_cotisation->formate() }}, {{ $cycle->periodicite->libelle() }}</li>
                    @empty
                        <li class="list-group-item text-body-secondary">Aucun cycle.</li>
                    @endforelse
                </ul>
            </div>

            @can('update', $groupe)
                <form method="POST" action="{{ route('tontines.cycles.store', $groupe) }}" class="card card-body">
                    @csrf
                    <h2 class="h6">Préparer un cycle</h2>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label for="devise" class="form-label small">Devise</label>
                            <select id="devise" name="devise" class="form-select">
                                @foreach (App\Support\Montant\Devise::cases() as $devise)
                                    <option value="{{ $devise->value }}" @selected(old('devise') === $devise->value)>{{ $devise->value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="periodicite" class="form-label small">Périodicité</label>
                            <select id="periodicite" name="periodicite" @class(['form-select', 'is-invalid' => $errors->has('periodicite')])>
                                @foreach (App\Domain\Tontines\Periodicite::cases() as $periodicite)
                                    <option value="{{ $periodicite->value }}" @selected(old('periodicite') === $periodicite->value)>{{ $periodicite->libelle() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="date_debut" class="form-label small">Première séance</label>
                            <input id="date_debut" name="date_debut" type="date" value="{{ old('date_debut') }}" required class="form-control">
                        </div>
                        @foreach (['montant_cotisation' => $groupe->type === App\Domain\Tontines\TypeGroupe::Avec ? 'Prix de la part' : 'Cotisation',
                                   'cotisation_fonds_social' => 'Fonds social / séance', 'penalite_absence' => 'Pénalité absence',
                                   'penalite_retard' => 'Pénalité retard', 'penalite_impaye' => 'Pénalité impayé'] as $champ => $libelle)
                            <div class="col-md-4">
                                <label for="{{ $champ }}" class="form-label small">{{ $libelle }}</label>
                                <input id="{{ $champ }}" name="{{ $champ }}" value="{{ old($champ, '0') }}" inputmode="decimal" required
                                       @class(['form-control', 'is-invalid' => $errors->has($champ)])>
                                @error($champ) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @endforeach
                        @if ($groupe->type === App\Domain\Tontines\TypeGroupe::Avec)
                            <div class="col-md-4">
                                <label for="nombre_seances" class="form-label small">Nombre de séances</label>
                                <input id="nombre_seances" name="nombre_seances" type="number" min="1" value="{{ old('nombre_seances') }}"
                                       @class(['form-control', 'is-invalid' => $errors->has('nombre_seances')])>
                                @error('nombre_seances') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        @else
                            <div class="col-md-4">
                                <label for="mode_tours" class="form-label small">Attribution des tours</label>
                                <select id="mode_tours" name="mode_tours" @class(['form-select', 'is-invalid' => $errors->has('mode_tours')])>
                                    @foreach (App\Domain\Tontines\ModeTours::cases() as $mode)
                                        <option value="{{ $mode->value }}" @selected(old('mode_tours') === $mode->value)>{{ $mode->libelle() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                    <div class="mt-3"><button type="submit" class="btn btn-primary">Préparer</button></div>
                </form>
            @endcan
        </div>
    </div>
@endsection
