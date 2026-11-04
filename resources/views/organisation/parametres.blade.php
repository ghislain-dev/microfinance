@extends('layouts.app')

@section('titre', 'Organisation')

@section('contenu')
    <h1 class="h3 mb-4">{{ $organisation->nom }} <span class="text-body-secondary fs-6">{{ $organisation->code }}</span></h1>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif

    <form method="POST" action="{{ route('organisation.logo') }}" enctype="multipart/form-data" class="card card-body">
        @csrf
        <h2 class="h5">Logo des reçus</h2>
        <p class="text-body-secondary small">{{ $organisation->logo_chemin ? 'Un logo est enregistré.' : 'Aucun logo pour l’instant.' }}
            PNG ou JPEG, 512 Ko au plus.</p>
        <input type="file" name="logo" accept="image/png,image/jpeg" required
               @class(['form-control', 'is-invalid' => $errors->has('logo')])>
        @error('logo') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="mt-3"><button type="submit" class="btn btn-primary">Envoyer</button></div>
    </form>

    <form method="POST" action="{{ route('organisation.rappels') }}" class="card card-body mt-4">
        @csrf
        <h2 class="h6">Rappels aux membres (application mobile)</h2>
        <label for="rappel_echeance_jours" class="form-label">Rappel d’échéance de prêt, en jours avant la date</label>
        <div class="d-flex gap-2">
            <input id="rappel_echeance_jours" name="rappel_echeance_jours" type="number" min="1" max="30" placeholder="Vide : aucun rappel"
                   value="{{ old('rappel_echeance_jours', $organisation->getAttribute('rappel_echeance_jours')) }}"
                   @class(['form-control w-auto', 'is-invalid' => $errors->has('rappel_echeance_jours')])>
            <button type="submit" class="btn btn-outline-primary">Enregistrer</button>
        </div>
        @error('rappel_echeance_jours') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </form>
@endsection
