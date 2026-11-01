@extends('layouts.app')

@section('titre', 'Tontines')

@section('contenu')
    <h1 class="h3 mb-4">Tontines</h1>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif

    @can('create', App\Domain\Tontines\Models\Groupe::class)
        <form method="POST" action="{{ route('tontines.store') }}" class="card card-body mb-4">
            @csrf
            <h2 class="h6">Nouveau groupe</h2>
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="agence_id" class="form-label small">Agence</label>
                    <select id="agence_id" name="agence_id" required class="form-select">
                        @foreach ($agences as $agence)
                            <option value="{{ $agence->id }}" @selected((int) old('agence_id') === $agence->id)>{{ $agence->code }} — {{ $agence->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="code" class="form-label small">Code</label>
                    <input id="code" name="code" value="{{ old('code') }}" maxlength="20" required placeholder="MAMANS"
                           @class(['form-control', 'is-invalid' => $errors->has('code')])>
                </div>
                <div class="col-md-3">
                    <label for="nom" class="form-label small">Nom</label>
                    <input id="nom" name="nom" value="{{ old('nom') }}" maxlength="100" required class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="type" class="form-label small">Type</label>
                    <select id="type" name="type" class="form-select">
                        @foreach (App\Domain\Tontines\TypeGroupe::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->libelle() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-primary">Créer</button></div>
            </div>
            @if ($errors->any()) <div class="text-danger small mt-2">{{ $errors->first() }}</div> @endif
        </form>
    @endcan

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Agence</th><th class="text-end">Membres</th></tr></thead>
                <tbody>
                    @forelse ($groupes as $groupe)
                        <tr>
                            <td><a href="{{ route('tontines.show', $groupe) }}">{{ $groupe->code }}</a></td>
                            <td>{{ $groupe->nom }}</td>
                            <td>{{ $groupe->type->libelle() }}</td>
                            <td>{{ $groupe->agence?->code }}</td>
                            <td class="text-end">{{ $groupe->membres_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">Aucun groupe.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $groupes->links() }}</div>
@endsection
