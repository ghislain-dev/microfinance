@extends('layouts.invite')

@section('titre', 'Code de connexion')

@section('contenu')
    <h1 class="h4 mb-2">Code de connexion</h1>
    <p class="text-body-secondary">Saisissez le code à {{ config('otp.longueur') }} chiffres que nous venons de vous envoyer.</p>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif
    @error('code')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ url('/connexion/code') }}">
        @csrf
        <div class="mb-4">
            <label for="code" class="form-label">Code</label>
            <input id="code" name="code" inputmode="numeric" pattern="\d{{ '{' }}{{ config('otp.longueur') }}{{ '}' }}"
                   maxlength="{{ config('otp.longueur') }}" class="form-control form-control-lg code-otp"
                   required autofocus autocomplete="one-time-code">
        </div>
        <button type="submit" class="btn btn-primary w-100">Valider</button>
    </form>

    <form method="POST" action="{{ route('connexion.code.renvoyer') }}" class="mt-3 text-center">
        @csrf
        <button type="submit" class="btn btn-link btn-sm">Recevoir un nouveau code</button>
    </form>
@endsection
