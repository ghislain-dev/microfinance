@extends('layouts.app')

@section('titre', $produit ? 'Modifier '.$produit->code : 'Nouveau produit de crédit')

@php
    $pourcentage = fn (?int $pb) => $pb === null ? '' : substr(App\Support\Montant\Pourcentage::formate($pb), 0, -2);
    $montant = fn (?App\Support\Montant\Montant $m) => $m === null ? '' : substr($m->formate(), 0, -4);
@endphp

@section('contenu')
    <h1 class="h3 mb-4">{{ $produit ? 'Modifier le produit '.$produit->code : 'Nouveau produit de crédit' }}</h1>

    <form method="POST" action="{{ $produit ? route('credit.produits.update', $produit) : route('credit.produits.store') }}" class="card card-body">
        @csrf
        @if ($produit) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-3">
                <label for="code" class="form-label">Code</label>
                <input id="code" name="code" value="{{ old('code', $produit?->code) }}" maxlength="10" placeholder="PME"
                       @disabled($produit) @class(['form-control', 'is-invalid' => $errors->has('code')])>
                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="libelle" class="form-label">Libellé</label>
                <input id="libelle" name="libelle" value="{{ old('libelle', $produit?->libelle) }}" maxlength="100" required
                       @class(['form-control', 'is-invalid' => $errors->has('libelle')])>
                @error('libelle') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="devise" class="form-label">Devise</label>
                <select id="devise" name="devise" @disabled($produit) @class(['form-select', 'is-invalid' => $errors->has('devise')])>
                    @foreach (App\Support\Montant\Devise::cases() as $devise)
                        <option value="{{ $devise->value }}" @selected(old('devise', $produit?->devise->value) === $devise->value)>{{ $devise->value }}</option>
                    @endforeach
                </select>
                @error('devise') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @if ($produit) <div class="form-text">Le code et la devise ne changent pas.</div> @endif
            </div>

            <div class="col-md-3">
                <label for="taux_mensuel" class="form-label">Taux d’intérêt mensuel (%)</label>
                <input id="taux_mensuel" name="taux_mensuel" inputmode="decimal" required value="{{ old('taux_mensuel', $pourcentage($produit?->taux_mensuel_pb)) }}"
                       @class(['form-control', 'is-invalid' => $errors->has('taux_mensuel')])>
                @error('taux_mensuel') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Sur le capital restant dû ; × 12 / 52 chaque semaine.</div>
            </div>
            <div class="col-md-3">
                <label for="periodicite" class="form-label">Échéances</label>
                <select id="periodicite" name="periodicite" @class(['form-select', 'is-invalid' => $errors->has('periodicite')])>
                    @foreach (App\Domain\Credit\Periodicite::cases() as $p)
                        <option value="{{ $p->value }}" @selected(old('periodicite', $produit?->periodicite->value ?? 'mensuelle') === $p->value)>{{ $p->libelle() }}</option>
                    @endforeach
                </select>
                @error('periodicite') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="duree_max" class="form-label">Nombre d’échéances au plus</label>
                <input id="duree_max" name="duree_max" type="number" min="1" max="520" required value="{{ old('duree_max', $produit?->duree_max) }}"
                       @class(['form-control', 'is-invalid' => $errors->has('duree_max')])>
                @error('duree_max') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="anciennete_mois" class="form-label">Ancienneté minimale (mois)</label>
                <input id="anciennete_mois" name="anciennete_mois" type="number" min="0" max="600" required value="{{ old('anciennete_mois', $produit?->anciennete_mois ?? 0) }}"
                       @class(['form-control', 'is-invalid' => $errors->has('anciennete_mois')])>
                @error('anciennete_mois') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label for="montant_minimum" class="form-label">Montant minimum</label>
                <input id="montant_minimum" name="montant_minimum" inputmode="decimal" required value="{{ old('montant_minimum', $montant($produit?->montant_minimum)) }}"
                       @class(['form-control', 'is-invalid' => $errors->has('montant_minimum')])>
                @error('montant_minimum') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="montant_maximum" class="form-label">Montant maximum</label>
                <input id="montant_maximum" name="montant_maximum" inputmode="decimal" required value="{{ old('montant_maximum', $montant($produit?->montant_maximum)) }}"
                       @class(['form-control', 'is-invalid' => $errors->has('montant_maximum')])>
                @error('montant_maximum') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="multiple_epargne" class="form-label">Plafond (× épargne)</label>
                <input id="multiple_epargne" name="multiple_epargne" type="number" min="1" max="100" required value="{{ old('multiple_epargne', $produit?->multiple_epargne) }}"
                       @class(['form-control', 'is-invalid' => $errors->has('multiple_epargne')])>
                @error('multiple_epargne') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="garantie_epargne" class="form-label">Épargne bloquée en garantie (%)</label>
                <input id="garantie_epargne" name="garantie_epargne" inputmode="decimal" required value="{{ old('garantie_epargne', $pourcentage($produit?->garantie_epargne_pb)) }}"
                       @class(['form-control', 'is-invalid' => $errors->has('garantie_epargne')])>
                @error('garantie_epargne') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-3">
                <label for="penalite_jour" class="form-label">Pénalité par jour de retard (%)</label>
                <input id="penalite_jour" name="penalite_jour" inputmode="decimal" required value="{{ old('penalite_jour', $pourcentage($produit?->penalite_jour_pb)) }}"
                       @class(['form-control', 'is-invalid' => $errors->has('penalite_jour')])>
                @error('penalite_jour') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="form-text">Sur le capital impayé de l’échéance.</div>
            </div>
            <div class="col-md-3">
                <label for="delai_grace_jours" class="form-label">Délai de grâce (jours)</label>
                <input id="delai_grace_jours" name="delai_grace_jours" type="number" min="0" max="365" required value="{{ old('delai_grace_jours', $produit?->delai_grace_jours ?? 0) }}"
                       @class(['form-control', 'is-invalid' => $errors->has('delai_grace_jours')])>
                @error('delai_grace_jours') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label for="delai_saisie_garantie_jours" class="form-label">Prélever la garantie après (jours de retard)</label>
                <input id="delai_saisie_garantie_jours" name="delai_saisie_garantie_jours" type="number" min="1" max="3650" placeholder="Vide : jamais"
                       value="{{ old('delai_saisie_garantie_jours', $produit?->delai_saisie_garantie_jours) }}"
                       @class(['form-control', 'is-invalid' => $errors->has('delai_saisie_garantie_jours')])>
                @error('delai_saisie_garantie_jours') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            @if ($produit)
                <div class="col-12 form-check ms-2">
                    <input type="hidden" name="actif" value="0">
                    <input class="form-check-input" type="checkbox" name="actif" value="1" id="actif" @checked(old('actif', $produit->actif))>
                    <label class="form-check-label" for="actif">Proposé aux nouvelles demandes</label>
                </div>
            @endif
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="{{ route('credit.produits.index') }}" class="btn btn-link">Annuler</a>
        </div>
    </form>
@endsection
