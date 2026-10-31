@extends('layouts.app')

@section('titre', 'Ouvrir '.$caisse->code)

@section('contenu')
    <h1 class="h3 mb-1">Ouvrir la caisse {{ $caisse->code }}</h1>
    <p class="text-body-secondary mb-4">Comptez les billets présents dans la caisse. Un écart avec le solde attendu
        bloquera la session jusqu’à la validation du gérant.</p>

    <form method="POST" action="{{ url("/caisses/{$caisse->id}/ouvrir") }}" class="card card-body">
        @csrf
        @include('caisses._billetage')
        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Ouvrir la session</button>
            <a href="{{ route('caisses.index') }}" class="btn btn-link">Annuler</a>
        </div>
    </form>
@endsection
