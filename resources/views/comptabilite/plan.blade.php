@extends('layouts.app')

@section('titre', 'Plan SYSCOHADA')

@section('contenu')
    <h1 class="h3 mb-3">Comptabilité</h1>
    @include('comptabilite._onglets')

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <p class="small text-body-secondary">Proposition par défaut à faire valider ; chaque rubrique du plan interne est regroupée sous ce numéro dans la balance et le grand livre.</p>

    <div class="card">
        <ul class="list-group list-group-flush">
            @foreach ($correspondances as $rubrique => [$numero, $libelle])
                <li class="list-group-item">
                    @can('parametrer', App\Domain\Comptabilite\Models\CorrespondanceSyscohada::class)
                        <form method="POST" action="{{ route('comptabilite.plan') }}" class="row g-2 align-items-center">
                            @csrf
                            <input type="hidden" name="rubrique" value="{{ $rubrique }}">
                            <div class="col-md-3 small text-body-secondary">{{ $rubrique }}</div>
                            <div class="col-md-2"><input name="numero" value="{{ $numero }}" maxlength="8" required aria-label="Numéro SYSCOHADA de {{ $rubrique }}" class="form-control form-control-sm"></div>
                            <div class="col-md-5"><input name="libelle" value="{{ $libelle }}" maxlength="150" required aria-label="Libellé de {{ $rubrique }}" class="form-control form-control-sm"></div>
                            <div class="col-md-2"><button type="submit" class="btn btn-outline-primary btn-sm">Enregistrer</button></div>
                        </form>
                    @else
                        <strong>{{ $numero }}</strong> {{ $libelle }} <span class="small text-body-secondary">({{ $rubrique }})</span>
                    @endcan
                </li>
            @endforeach
        </ul>
    </div>
@endsection
