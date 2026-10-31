@extends('layouts.app')

@section('titre', 'Créer une caisse')

@section('contenu')
    <h1 class="h3 mb-4">Créer une caisse</h1>

    <form method="POST" action="{{ route('caisses.store') }}" class="card card-body">
        @csrf
        <div class="row g-3">
            <div class="col-md-4">
                <label for="agence_id" class="form-label">Agence</label>
                <select id="agence_id" name="agence_id" required @class(['form-select', 'is-invalid' => $errors->has('agence_id')])>
                    <option value=""></option>
                    @foreach ($agences as $agence)
                        <option value="{{ $agence->id }}" @selected((int) old('agence_id') === $agence->id)>{{ $agence->code }} — {{ $agence->nom }}</option>
                    @endforeach
                </select>
                @error('agence_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="code" class="form-label">Code</label>
                <input id="code" name="code" value="{{ old('code') }}" maxlength="20" required placeholder="G1"
                       @class(['form-control', 'is-invalid' => $errors->has('code')])>
                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label for="libelle" class="form-label">Libellé</label>
                <input id="libelle" name="libelle" value="{{ old('libelle') }}" maxlength="100" required placeholder="Guichet 1"
                       @class(['form-control', 'is-invalid' => $errors->has('libelle')])>
                @error('libelle') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <p class="form-text mt-3 mb-0">Un compte de caisse est ouvert automatiquement pour chaque devise (USD et CDF).</p>
        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Créer</button>
            <a href="{{ route('caisses.index') }}" class="btn btn-link">Annuler</a>
        </div>
    </form>
@endsection
