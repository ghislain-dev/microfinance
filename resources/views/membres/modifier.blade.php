@extends('layouts.app')

@section('titre', 'Modifier '.$membre->numero)

@section('contenu')
    <h1 class="h3 mb-4">Modifier la fiche {{ $membre->numero }}</h1>

    <form method="POST" action="{{ route('membres.update', $membre) }}" class="card card-body">
        @csrf
        @method('PUT')

        @include('membres._formulaire', ['membre' => $membre])

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="{{ route('membres.show', $membre) }}" class="btn btn-link">Annuler</a>
        </div>
    </form>
@endsection
