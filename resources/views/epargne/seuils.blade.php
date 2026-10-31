@extends('layouts.app')

@section('titre', 'Seuils de validation')

@section('contenu')
    <h1 class="h3 mb-1">Seuils de validation</h1>
    <p class="text-body-secondary mb-4">Au-delà de ce montant, un retrait ou un virement doit être validé par le gérant ou le comptable.
        Sans seuil, aucune validation n’est demandée.</p>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif

    <div class="row g-3">
        @foreach (App\Support\Montant\Devise::cases() as $devise)
            <div class="col-md-6">
                <form method="POST" action="{{ route('epargne.seuils') }}" class="card card-body">
                    @csrf
                    <input type="hidden" name="devise" value="{{ $devise->value }}">
                    <h2 class="h6">{{ $devise->value }}</h2>
                    <p class="small mb-2">Actuel : {{ isset($seuils[$devise->value]) ? $seuils[$devise->value]->montant->formate() : 'aucun' }}</p>
                    <div class="d-flex gap-2">
                        <input name="montant" inputmode="decimal" required placeholder="0,00" class="form-control" aria-label="Seuil {{ $devise->value }}">
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
    @error('montant') <div class="alert alert-danger mt-3" role="alert">{{ $message }}</div> @enderror
@endsection
