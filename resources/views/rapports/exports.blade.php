@extends('layouts.app')

@section('titre', 'Rapports')

@section('contenu')
    <h1 class="h3 mb-4">Rapports</h1>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('exports.index') }}" class="card card-body mb-4">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="type" class="form-label small">Rapport</label>
                <select id="type" name="type" class="form-select">
                    @foreach ($types as $code => $libelle)
                        <option value="{{ $code }}" @selected(old('type') === $code)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <label for="format" class="form-label small">Format</label>
                <select id="format" name="format" class="form-select"><option value="pdf">PDF</option><option value="csv">CSV</option></select>
            </div>
            <div class="col-md-1">
                <label for="devise" class="form-label small">Devise</label>
                <select id="devise" name="devise" class="form-select">
                    @foreach (App\Support\Montant\Devise::cases() as $d) <option value="{{ $d->value }}">{{ $d->value }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="du" class="form-label small">Du</label>
                <input id="du" type="date" name="du" value="{{ old('du', now()->startOfMonth()->toDateString()) }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label for="au" class="form-label small">Au</label>
                <input id="au" type="date" name="au" value="{{ old('au', now()->toDateString()) }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label for="rubrique" class="form-label small">Grand livre : rubrique</label>
                <select id="rubrique" name="rubrique" class="form-select">
                    @foreach ($rubriques as $r) <option value="{{ $r }}">{{ $r }}</option> @endforeach
                </select>
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-primary">Préparer</button></div>
        </div>
        <div class="form-text">Le relevé d’un membre se demande depuis la page de son compte d’épargne.</div>
    </form>

    <div class="card">
        <ul class="list-group list-group-flush">
            @forelse ($exports as $export)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>{{ $export->libelle() }} · {{ strtoupper($export->format) }} · {{ $export->created_at?->format('d/m/Y H:i') }}
                        @if ($export->statut === 'echec') <span class="text-danger small">— échec</span> @endif</span>
                    @if ($export->statut === 'pret')
                        <a href="{{ route('exports.fichier', $export) }}" class="btn btn-outline-primary btn-sm">Télécharger</a>
                    @elseif ($export->statut === 'en_attente')
                        <span class="badge text-bg-light border">En préparation…</span>
                    @endif
                </li>
            @empty
                <li class="list-group-item text-body-secondary">Aucun rapport demandé.</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-3">{{ $exports->links() }}</div>
@endsection
