@extends('layouts.app')

@section('titre', 'Inscrire un membre')

@section('contenu')
    <h1 class="h3 mb-4">Inscrire un membre</h1>

    <form method="POST" action="{{ route('membres.store') }}" class="card card-body">
        @csrf
        <div class="mb-3 col-md-4">
            <label for="agence_id" class="form-label">Agence <span class="text-danger">*</span></label>
            <select id="agence_id" name="agence_id" required @class(['form-select', 'is-invalid' => $errors->has('agence_id')])>
                <option value=""></option>
                @foreach ($agences as $agence)
                    <option value="{{ $agence->id }}" @selected((int) old('agence_id') === $agence->id)>{{ $agence->code }} — {{ $agence->nom }}</option>
                @endforeach
            </select>
            @error('agence_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">Le numéro du membre sera attribué dans cette agence.</div>
        </div>

        @include('membres._formulaire', ['membre' => null])

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Inscrire</button>
            <a href="{{ route('membres.index') }}" class="btn btn-link">Annuler</a>
        </div>
    </form>
@endsection
