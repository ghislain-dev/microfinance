@extends('layouts.invite')

@section('titre', 'Tableau de bord')

@section('contenu')
    <h1>Tableau de bord</h1>
    <p>Bonjour {{ auth()->user()?->name }}.</p>

    <form method="POST" action="{{ route('deconnexion') }}">
        @csrf
        <button type="submit">Se déconnecter</button>
    </form>
@endsection
