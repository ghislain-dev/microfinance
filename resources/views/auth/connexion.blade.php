@extends('layouts.invite')

@section('titre', 'Connexion')

@section('contenu')
    <h1>Connexion</h1>

    @if ($errors->any())
        <div role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ url('/connexion') }}">
        @csrf
        <label for="email">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Mot de passe</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <button type="submit">Continuer</button>
    </form>
@endsection
