@extends('layouts.invite')

@section('titre', 'Code de connexion')

@section('contenu')
    <h1>Code de connexion</h1>
    <p>Saisissez le code à {{ config('otp.longueur') }} chiffres que nous venons de vous envoyer.</p>

    @if (session('statut'))
        <div role="status">{{ session('statut') }}</div>
    @endif
    @error('code')
        <div role="alert">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ url('/connexion/code') }}">
        @csrf
        <label for="code">Code</label>
        <input id="code" name="code" inputmode="numeric" pattern="\d{{ '{' }}{{ config('otp.longueur') }}{{ '}' }}"
               maxlength="{{ config('otp.longueur') }}" required autofocus autocomplete="one-time-code">
        <button type="submit">Valider</button>
    </form>

    <form method="POST" action="{{ route('connexion.code.renvoyer') }}">
        @csrf
        <button type="submit">Recevoir un nouveau code</button>
    </form>
@endsection
