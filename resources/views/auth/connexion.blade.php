@extends('layouts.invite')

@section('titre', 'Connexion')

@section('contenu')
    <h1 class="h4 mb-4">Connexion</h1>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ url('/connexion') }}">
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control"
                   required autofocus autocomplete="username">
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">Mot de passe</label>
            <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-primary w-100">Continuer</button>
    </form>
@endsection
