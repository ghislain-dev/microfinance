@extends('layouts.app')

@section('titre', 'Nouvelle demande de prêt')

@section('contenu')
    <h1 class="h3 mb-4">Nouvelle demande de prêt</h1>

    <form method="GET" action="{{ route('credit.demandes.create') }}" class="row g-2 mb-4 align-items-end">
        <div class="col-md-4">
            <label for="membre" class="form-label small">Numéro du membre</label>
            <input id="membre" name="membre" value="{{ $numero }}" maxlength="40" required placeholder="BUK-000045" class="form-control">
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Rechercher</button></div>
        @if ($numero !== '' && $membre === null) <div class="col-12 text-danger small">Aucun membre n° {{ $numero }}.</div> @endif
    </form>

    @if ($membre)
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('credit.demandes.store') }}" enctype="multipart/form-data" class="card card-body">
            @csrf
            <input type="hidden" name="membre_id" value="{{ $membre->id }}">
            <h2 class="h6 mb-3">{{ $membre->numero }} — {{ $membre->nomComplet() }} <span class="badge text-bg-light border">{{ $membre->statut->libelle() }}</span></h2>

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="produit_credit_id" class="form-label">Produit</label>
                    <select id="produit_credit_id" name="produit_credit_id" required @class(['form-select', 'is-invalid' => $errors->has('produit_credit_id')])>
                        @foreach ($produits as $produit)
                            <option value="{{ $produit->id }}" @selected((int) old('produit_credit_id') === $produit->id)>
                                {{ $produit->libelle }} — {{ App\Support\Montant\Pourcentage::formate($produit->taux_mensuel_pb) }} / mois, {{ mb_strtolower($produit->periodicite->libelle()) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="compte_epargne_id" class="form-label">Compte d’épargne (versement et garantie)</label>
                    <select id="compte_epargne_id" name="compte_epargne_id" required @class(['form-select', 'is-invalid' => $errors->has('compte_epargne_id')])>
                        @foreach ($comptes as $compte)
                            <option value="{{ $compte->id }}" @selected((int) old('compte_epargne_id') === $compte->id)>{{ $compte->numero }} ({{ $compte->type->devise->value }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="montant" class="form-label">Montant</label>
                    <input id="montant" name="montant" inputmode="decimal" required value="{{ old('montant') }}" @class(['form-control', 'is-invalid' => $errors->has('montant')])>
                    @error('montant') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="nombre_echeances" class="form-label">Nombre d’échéances</label>
                    <input id="nombre_echeances" name="nombre_echeances" type="number" min="1" max="520" required value="{{ old('nombre_echeances') }}"
                           @class(['form-control', 'is-invalid' => $errors->has('nombre_echeances')])>
                    @error('nombre_echeances') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="methode" class="form-label">Échéancier</label>
                    <select id="methode" name="methode" @class(['form-select', 'is-invalid' => $errors->has('methode')])>
                        @foreach (App\Domain\Credit\MethodeCalcul::cases() as $m)
                            <option value="{{ $m->value }}" @selected(old('methode') === $m->value)>{{ $m->libelle() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="objet" class="form-label">Objet du prêt</label>
                    <input id="objet" name="objet" maxlength="500" required value="{{ old('objet') }}" @class(['form-control', 'is-invalid' => $errors->has('objet')])>
                </div>
            </div>

            <h3 class="h6 mt-4">Garanties</h3>
            <p class="small text-body-secondary mb-2">L’épargne bloquée est calculée au décaissement selon le produit. Cautions et biens : facultatifs.</p>
            @foreach ([0, 1] as $i)
                <div class="row g-2 mb-2">
                    <input type="hidden" name="garanties[{{ $i }}][type]" value="caution">
                    <div class="col-md-4">
                        <input name="garanties[{{ $i }}][membre_numero]" maxlength="40" value="{{ old("garanties.$i.membre_numero") }}" placeholder="N° du membre caution"
                               aria-label="Numéro du membre caution" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <input name="garanties[{{ $i }}][montant]" inputmode="decimal" value="{{ old("garanties.$i.montant") }}" placeholder="Montant cautionné"
                               aria-label="Montant cautionné" @class(['form-control form-control-sm', 'is-invalid' => $errors->has("garanties.$i.montant")])>
                    </div>
                </div>
            @endforeach
            <div class="row g-2">
                <input type="hidden" name="garanties[2][type]" value="bien">
                <div class="col-md-4">
                    <input name="garanties[2][description]" maxlength="500" value="{{ old('garanties.2.description') }}" placeholder="Bien en garantie (description)"
                           aria-label="Description du bien" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <input name="garanties[2][valeur]" inputmode="decimal" value="{{ old('garanties.2.valeur') }}" placeholder="Valeur estimée"
                           aria-label="Valeur estimée du bien" @class(['form-control form-control-sm', 'is-invalid' => $errors->has('garanties.2.valeur')])>
                </div>
                <div class="col-md-5">
                    <input type="file" name="garanties[2][piece]" accept=".pdf,.jpg,.jpeg,.png" aria-label="Pièce jointe (PDF, JPEG, PNG)"
                           @class(['form-control form-control-sm', 'is-invalid' => $errors->has('garanties.2.piece')])>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Enregistrer la demande</button>
                <a href="{{ route('credit.index') }}" class="btn btn-link">Annuler</a>
            </div>
        </form>
    @endif
@endsection
