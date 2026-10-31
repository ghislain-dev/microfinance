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
@endsection
