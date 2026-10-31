@extends('layouts.app')

@section('titre', $type ? 'Modifier '.$type->code : 'Nouveau produit d’épargne')

@section('contenu')
    <h1 class="h3 mb-4">{{ $type ? 'Modifier le produit '.$type->code : 'Nouveau produit d’épargne' }}</h1>

    <form method="POST" action="{{ $type ? route('epargne.types.update', $type) : route('epargne.types.store') }}" class="card card-body">
        @csrf
        @if ($type) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-3">
                <label for="code" class="form-label">Code</label>
                <input id="code" name="code" value="{{ old('code', $type?->code) }}" maxlength="10" placeholder="EPV"
                       @disabled($type) @class(['form-control', 'is-invalid' => $errors->has('code')])>
                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="libelle" class="form-label">Libellé</label>
                <input id="libelle" name="libelle" value="{{ old('libelle', $type?->libelle) }}" maxlength="100" required
                       @class(['form-control', 'is-invalid' => $errors->has('libelle')])>
                @error('libelle') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="devise" class="form-label">Devise</label>
                <select id="devise" name="devise" @disabled($type) @class(['form-select', 'is-invalid' => $errors->has('devise')])>
                    @foreach (App\Support\Montant\Devise::cases() as $devise)
                        <option value="{{ $devise->value }}" @selected(old('devise', $type?->devise->value) === $devise->value)>{{ $devise->value }}</option>
                    @endforeach
                </select>
                @error('devise') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @if ($type) <div class="form-text">Le code et la devise ne changent pas.</div> @endif
            </div>

            @foreach (['solde_minimum' => 'Solde minimum', 'depot_minimum' => 'Dépôt minimum', 'frais_retrait' => 'Frais de retrait'] as $champ => $libelle)
                <div class="col-md-3">
                    <label for="{{ $champ }}" class="form-label">{{ $libelle }}</label>
                    <input id="{{ $champ }}" name="{{ $champ }}" inputmode="decimal" required
                           value="{{ old($champ, $type ? substr($type->{$champ}->formate(), 0, -4) : '0') }}"
                           @class(['form-control', 'is-invalid' => $errors->has($champ)])>
                    @error($champ) <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endforeach
            <div class="col-md-3">
                <label for="duree_blocage_mois" class="form-label">Blocage (mois)</label>
                <input id="duree_blocage_mois" name="duree_blocage_mois" type="number" min="1" max="600"
                       value="{{ old('duree_blocage_mois', $type?->duree_blocage_mois) }}" placeholder="Vide : retraits libres"
                       @class(['form-control', 'is-invalid' => $errors->has('duree_blocage_mois')])>
                @error('duree_blocage_mois') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            @if ($type)
                <div class="col-12 form-check ms-2">
                    <input type="hidden" name="actif" value="0">
                    <input class="form-check-input" type="checkbox" name="actif" value="1" id="actif" @checked(old('actif', $type->actif))>
                    <label class="form-check-label" for="actif">Proposé aux nouveaux comptes</label>
                </div>
            @endif
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="{{ route('epargne.types.index') }}" class="btn btn-link">Annuler</a>
        </div>
    </form>
@endsection
