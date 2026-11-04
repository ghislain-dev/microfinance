@extends('layouts.app')

@section('titre', 'Clôtures mensuelles')

@section('contenu')
    <h1 class="h3 mb-3">Clôtures mensuelles</h1>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <p>Période clôturée jusqu’au : <strong>{{ $derniere ? \Carbon\CarbonImmutable::parse($derniere)->format('d/m/Y') : 'aucune clôture' }}</strong>.
        Aucune écriture n’est plus possible avant cette date.</p>

    @can('cloturer', App\Domain\Comptabilite\Models\DemandeCloture::class)
        <form method="POST" action="{{ route('comptabilite.clotures') }}" class="card card-body mb-4">
            @csrf
            <h2 class="h6">Demander la clôture d’un mois</h2>
            <p class="small text-body-secondary">Bloquée si une session de caisse est ouverte ou en écart, un versement de collecteur en écart,
                un lot d’intérêts ou un partage de tontine à valider, ou un écart à la vérification des soldes. Le gérant valide ensuite.</p>
            <div class="d-flex gap-2">
                <input type="month" name="mois" value="{{ old('mois', now()->subMonthNoOverflow()->format('Y-m')) }}" required aria-label="Mois à clôturer" class="form-control w-auto">
                <button type="submit" class="btn btn-primary">Demander la clôture</button>
            </div>
        </form>
    @endcan

    <div class="card">
        <ul class="list-group list-group-flush">
            @forelse ($demandes as $demande)
                <li class="list-group-item">
                    Mois au {{ $demande->date_fin->format('d/m/Y') }} — demandé par {{ $utilisateurs[$demande->demande_par_id] ?? '?' }}
                    <span class="badge text-bg-{{ ['en_attente' => 'warning', 'validee' => 'success', 'rejetee' => 'danger'][$demande->statut] }}">
                        {{ ['en_attente' => 'À valider', 'validee' => 'Clôturé', 'rejetee' => 'Rejetée'][$demande->statut] }}</span>
                    @if ($demande->getAttribute('motif_decision')) <span class="small text-body-secondary">· {{ $demande->getAttribute('motif_decision') }}</span> @endif
                    @can('validerCloture', $demande)
                        <div class="row g-2 mt-1">
                            @foreach (['valider' => ['Valider', 'success'], 'rejeter' => ['Rejeter', 'outline-danger']] as $decision => [$libelle, $couleur])
                                <form method="POST" action="{{ route('comptabilite.clotures.decider', [$demande, $decision]) }}" class="col-md-6 d-flex gap-2">
                                    @csrf
                                    <input name="motif" required maxlength="500" placeholder="Motif" aria-label="Motif" class="form-control form-control-sm">
                                    <button type="submit" class="btn btn-{{ $couleur }} btn-sm">{{ $libelle }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endcan
                </li>
            @empty
                <li class="list-group-item text-body-secondary">Aucune demande.</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-3">{{ $demandes->links() }}</div>

    <div class="card mt-4">
        <div class="card-header">Affectation du résultat aux excédents à répartir</div>
        <div class="card-body">
            @if ($annee === null)
                <p class="text-body-secondary mb-0">Possible après la clôture du mois de décembre.</p>
            @else
                <p class="small">Résultat {{ $annee }} restant à affecter (produits − charges) :
                    @foreach ($restants as $restant) <strong>{{ $restant->formate() }}</strong>@if (! $loop->last) · @endif @endforeach.
                    Les excédents à répartir servent à la ristourne.</p>
                @can('affecter', App\Domain\Comptabilite\Models\AffectationResultat::class)
                    <form method="POST" action="{{ route('comptabilite.affectations') }}" class="row g-2 align-items-end">
                        @csrf
                        <input type="hidden" name="annee" value="{{ $annee }}">
                        <div class="col-md-2">
                            <label for="devise-affectation" class="form-label small">Devise</label>
                            <select id="devise-affectation" name="devise" class="form-select">
                                @foreach (App\Support\Montant\Devise::cases() as $d) <option value="{{ $d->value }}">{{ $d->value }}</option> @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="montant-affectation" class="form-label small">Montant à affecter</label>
                            <input id="montant-affectation" name="montant" inputmode="decimal" required class="form-control">
                        </div>
                        <div class="col-auto"><button type="submit" class="btn btn-outline-primary">Proposer</button></div>
                    </form>
                @endcan
            @endif
        </div>
        <ul class="list-group list-group-flush">
            @foreach ($affectations as $a)
                <li class="list-group-item">
                    {{ $a->annee }} · {{ $a->montant->formate() }} sur {{ $a->resultat->formate() }} — {{ $utilisateurs[$a->propose_par_id] ?? '?' }}
                    <span class="badge text-bg-{{ ['en_attente' => 'warning', 'validee' => 'success', 'rejetee' => 'danger'][$a->statut] }}">
                        {{ ['en_attente' => 'À valider', 'validee' => 'Affecté', 'rejetee' => 'Rejetée'][$a->statut] }}</span>
                    @can('validerAffectation', $a)
                        <div class="row g-2 mt-1">
                            @foreach (['valider' => ['Valider', 'success'], 'rejeter' => ['Rejeter', 'outline-danger']] as $decision => [$libelle, $couleur])
                                <form method="POST" action="{{ route('comptabilite.affectations.decider', [$a, $decision]) }}" class="col-md-6 d-flex gap-2">
                                    @csrf
                                    <input name="motif" required maxlength="500" placeholder="Motif" aria-label="Motif" class="form-control form-control-sm">
                                    <button type="submit" class="btn btn-{{ $couleur }} btn-sm">{{ $libelle }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endcan
                </li>
            @endforeach
        </ul>
    </div>
@endsection
