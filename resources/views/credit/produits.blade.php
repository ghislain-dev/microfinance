@extends('layouts.app')

@section('titre', 'Produits de crédit')

@section('contenu')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Produits de crédit</h1>
        @can('create', App\Domain\Credit\Models\ProduitCredit::class)
            <a href="{{ route('credit.produits.create') }}" class="btn btn-primary">Nouveau produit</a>
        @endcan
    </div>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr><th>Code</th><th>Libellé</th><th>Devise</th><th class="text-end">Taux mensuel</th><th>Échéances</th>
                        <th class="text-end">Montants</th><th class="text-end">Plafond</th><th class="text-end">Garantie</th>
                        <th class="text-end">Pénalité / jour</th><th>Grâce</th><th>Ancienneté</th><th>État</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($produits as $produit)
                        <tr>
                            <td>{{ $produit->code }}</td>
                            <td>{{ $produit->libelle }}</td>
                            <td>{{ $produit->devise->value }}</td>
                            <td class="text-end">{{ App\Support\Montant\Pourcentage::formate($produit->taux_mensuel_pb) }}</td>
                            <td>{{ $produit->periodicite->libelle() }}, {{ $produit->duree_max }} au plus</td>
                            <td class="text-end">{{ $produit->montant_minimum->formate() }} à {{ $produit->montant_maximum->formate() }}</td>
                            <td class="text-end">{{ $produit->multiple_epargne }} × l’épargne</td>
                            <td class="text-end">{{ App\Support\Montant\Pourcentage::formate($produit->garantie_epargne_pb) }}</td>
                            <td class="text-end">{{ App\Support\Montant\Pourcentage::formate($produit->penalite_jour_pb) }}</td>
                            <td>{{ $produit->delai_grace_jours }} j</td>
                            <td>{{ $produit->anciennete_mois }} mois</td>
                            <td>{{ $produit->actif ? 'Proposé' : 'Retiré' }}</td>
                            <td class="text-end"><a href="{{ route('credit.produits.edit', $produit) }}">Modifier</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="text-center text-body-secondary py-4">Aucun produit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mb-4">{{ $produits->links() }}</div>

    <div class="card card-body">
        <h2 class="h6">Seuil d’approbation du gérant</h2>
        <p class="small text-body-secondary">Au-delà de ce montant, le gérant approuve le prêt après le comité.</p>
        <ul class="small">
            @foreach (App\Support\Montant\Devise::cases() as $devise)
                <li>{{ $devise->value }} : {{ isset($seuils[$devise->value]) ? $seuils[$devise->value]->montant->formate() : 'aucun (le comité suffit)' }}</li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('credit.seuils') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2">
                <label for="seuil-devise" class="form-label small">Devise</label>
                <select id="seuil-devise" name="devise" class="form-select">
                    @foreach (App\Support\Montant\Devise::cases() as $devise)
                        <option value="{{ $devise->value }}">{{ $devise->value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="seuil-montant" class="form-label small">Montant</label>
                <input id="seuil-montant" name="montant" inputmode="decimal" required class="form-control">
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-outline-primary">Enregistrer</button></div>
        </form>
    </div>
@endsection
