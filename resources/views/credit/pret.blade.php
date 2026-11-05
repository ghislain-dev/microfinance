@extends('layouts.app')

@section('titre', 'Prêt '.$pret->numero)

@section('contenu')
    <h1 class="h3 mb-1">Prêt {{ $pret->numero }} <span class="badge text-bg-{{ $pret->statut->couleur() }} fs-6 align-middle">{{ $pret->statut->libelle() }}</span></h1>
    <p class="text-body-secondary mb-4">
        <a href="{{ route('membres.show', $pret->membre) }}">{{ $pret->membre->numero }} — {{ $pret->membre->nomComplet() }}</a> ·
        {{ $pret->produit->libelle }} · {{ $pret->montant->formate() }} ·
        {{ App\Support\Montant\Pourcentage::formate($pret->taux_mensuel_pb) }} par mois ·
        {{ $pret->nombre_echeances }} échéances ({{ mb_strtolower($pret->periodicite->libelle()) }}) · {{ $pret->methode->libelle() }}
    </p>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Dossier</div>
                <dl class="card-body row mb-0 small">
                    <dt class="col-5">Objet</dt><dd class="col-7">{{ $pret->objet }}</dd>
                    <dt class="col-5">Compte d’épargne</dt><dd class="col-7">{{ $pret->compteEpargne->numero }}</dd>
                    <dt class="col-5">Saisi par</dt><dd class="col-7">{{ $acteurs[$pret->saisi_par_id] ?? '—' }}, le {{ App\Support\Temps\Horloge::affiche($pret->created_at, 'd/m/Y') }}</dd>
                    @if ($pret->comite_par_id)<dt class="col-5">Comité</dt><dd class="col-7">{{ $acteurs[$pret->comite_par_id] ?? '—' }}</dd>@endif
                    @if ($pret->gerant_par_id)<dt class="col-5">Gérant</dt><dd class="col-7">{{ $acteurs[$pret->gerant_par_id] ?? '—' }}</dd>@endif
                    @if ($pret->getAttribute('motif_decision'))<dt class="col-5">Motif de la décision</dt><dd class="col-7">{{ $pret->getAttribute('motif_decision') }}</dd>@endif
                    @if ($pret->decaisse_par_id)
                        <dt class="col-5">Décaissé</dt><dd class="col-7">le {{ $pret->date_decaissement?->format('d/m/Y') }} par {{ $acteurs[$pret->decaisse_par_id] ?? '—' }}</dd>
                        <dt class="col-5">Épargne bloquée</dt><dd class="col-7">{{ $pret->garantie_epargne?->formate() }}</dd>
                        <dt class="col-5">Capital restant</dt><dd class="col-7">{{ $capitalRestant->formate() }}</dd>
                        <dt class="col-5">Reste dû</dt><dd class="col-7"><strong>{{ $resteDu->formate() }}</strong></dd>
                    @endif
                </dl>
                @if ($pret->analyse)
                    <div class="card-footer small"><strong>Analyse :</strong> {{ $pret->analyse }}</div>
                @endif
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Garanties</div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item">Épargne bloquée : {{ App\Support\Montant\Pourcentage::formate($pret->garantie_epargne_pb) }} du prêt, au décaissement</li>
                    @foreach ($pret->garanties as $garantie)
                        <li class="list-group-item">
                            @if ($garantie->type === 'caution')
                                Caution : {{ $garantie->membre?->numero }} — {{ $garantie->membre?->nomComplet() }}, {{ $garantie->montant?->formate() }}
                            @else
                                Bien : {{ $garantie->description }}@if ($garantie->montant), valeur estimée {{ $garantie->montant->formate() }}@endif
                                @if ($garantie->getAttribute('piece_chemin')) · <a href="{{ route('garanties.piece', $garantie) }}">pièce jointe</a>@endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    @can('analyser', $pret)
        <form method="POST" action="{{ route('prets.analyse', $pret) }}" class="card card-body mb-4">
            @csrf
            <label for="analyse" class="form-label">Analyse (activité, revenus, capacité de remboursement)</label>
            <textarea id="analyse" name="analyse" rows="3" maxlength="5000" required class="form-control mb-2">{{ old('analyse') }}</textarea>
            <div><button type="submit" class="btn btn-primary">Soumettre au comité</button></div>
        </form>
    @endcan

    @can('decider', $pret)
        <div class="card card-body mb-4">
            <h2 class="h6">{{ $pret->statut === App\Domain\Credit\StatutPret::Soumis ? 'Décision du comité' : 'Décision du gérant' }}</h2>
            <div class="row g-2">
                @foreach (['approuver' => ['Approuver', 'success'], 'rejeter' => ['Rejeter', 'outline-danger']] as $decision => [$libelle, $couleur])
                    <form method="POST" action="{{ route('prets.decider', [$pret, $decision]) }}" class="col-md-6 d-flex gap-2">
                        @csrf
                        <input name="motif" required maxlength="500" placeholder="Motif" class="form-control" aria-label="Motif">
                        <button type="submit" class="btn btn-{{ $couleur }}">{{ $libelle }}</button>
                    </form>
                @endforeach
            </div>
        </div>
    @endcan

    @can('decaisser', $pret)
        <form method="POST" action="{{ route('prets.decaissement', $pret) }}" class="card card-body mb-4">
            @csrf
            <p class="small text-body-secondary mb-2">Le montant est versé sur le compte {{ $pret->compteEpargne->numero }} ; l’épargne de garantie y est alors bloquée.</p>
            <div><button type="submit" class="btn btn-primary">Décaisser {{ $pret->montant->formate() }}</button></div>
        </form>
    @endcan

    @if ($saisie)
        <div class="card card-body mb-4 border-warning">
            <h2 class="h6">Prélèvement de la garantie demandé : {{ $saisie->montant->formate() }}</h2>
            <p class="small mb-2">{{ $saisie->getAttribute('motif') }} — {{ $acteurs[$saisie->demande_par_id] ?? '' }}</p>
            @can('validerSaisie', $saisie)
                <div class="row g-2">
                    @foreach (['valider' => ['Valider le prélèvement', 'success'], 'rejeter' => ['Rejeter', 'outline-danger']] as $decision => [$libelle, $couleur])
                        <form method="POST" action="{{ route('saisies-garantie.decider', [$saisie, $decision]) }}" class="col-md-6 d-flex gap-2">
                            @csrf
                            <input name="motif" required maxlength="500" placeholder="Motif" aria-label="Motif" class="form-control form-control-sm">
                            <button type="submit" class="btn btn-{{ $couleur }} btn-sm">{{ $libelle }}</button>
                        </form>
                    @endforeach
                </div>
            @endcan
        </div>
    @elseif ($pret->statut === App\Domain\Credit\StatutPret::EnCours && $pret->getAttribute('delai_saisie_garantie_jours') !== null)
        @can('saisirGarantie', $pret)
            <form method="POST" action="{{ route('prets.saisie-garantie', $pret) }}" class="card card-body mb-4">
                @csrf
                <h2 class="h6">Prélever la garantie</h2>
                <p class="small text-body-secondary">Possible après {{ $pret->getAttribute('delai_saisie_garantie_jours') }} jours de retard ; le comptable valide.
                    La garantie bloquée rembourse les échéances échues (pénalités, intérêts, capital).</p>
                <div class="d-flex gap-2">
                    <input name="motif" required maxlength="500" placeholder="Motif" aria-label="Motif du prélèvement" class="form-control">
                    <button type="submit" class="btn btn-outline-danger">Prélever la garantie</button>
                </div>
            </form>
        @endcan
    @endif

    @if ($echeances->isNotEmpty())
        @if ($pret->statut === App\Domain\Credit\StatutPret::EnCours)
            @can('rembourser', $pret)
                <form method="POST" action="{{ route('prets.remboursements', $pret) }}" class="card card-body mb-4">
                    @csrf
                    <input type="hidden" name="cle" value="{{ $cle }}">
                    <h2 class="h6">Remboursement</h2>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label for="montant" class="form-label small">Montant</label>
                            <input id="montant" name="montant" inputmode="decimal" required value="{{ old('montant') }}" @class(['form-control', 'is-invalid' => $errors->has('montant')])>
                        </div>
                        <div class="col-md-3">
                            <label for="mode" class="form-label small">Mode</label>
                            <select id="mode" name="mode" class="form-select">
                                @foreach (App\Domain\Credit\ModeRemboursement::cases() as $mode)
                                    <option value="{{ $mode->value }}">{{ $mode->libelle() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
                    </div>
                    <div class="form-text">Imputé aux pénalités, puis aux intérêts, puis au capital, échéance la plus ancienne d’abord.</div>
                </form>
            @endcan
        @endif

        <div class="card mb-4">
            <div class="card-header">Échéancier</div>
            <div class="table-responsive">
                <table class="table mb-0 small align-middle">
                    <thead><tr><th>N°</th><th>Date</th><th class="text-end">Capital</th><th class="text-end">Intérêt</th><th class="text-end">Pénalités</th>
                        <th class="text-end">Payé</th><th class="text-end">Reste dû</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($echeances as $e)
                            <tr>
                                <td>{{ $e->rang }}</td>
                                <td>{{ $e->date_echeance->format('d/m/Y') }}</td>
                                <td class="text-end">{{ $e->capital->formate() }}</td>
                                <td class="text-end">{{ $e->interet->formate() }}</td>
                                <td class="text-end">{{ $e->penalite->formate() }}</td>
                                <td class="text-end">{{ $e->paye()->formate() }}</td>
                                <td class="text-end">{{ $e->resteDu()->formate() }}</td>
                                <td>
                                    @if ($e->payee_le) <span class="badge text-bg-success">Payée</span>
                                    @elseif ($e->date_echeance->isPast()) <span class="badge text-bg-danger">En retard</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Remboursements</div>
            <div class="table-responsive">
                <table class="table mb-0 small">
                    <thead><tr><th>Date</th><th>Mode</th><th class="text-end">Montant</th><th class="text-end">Pénalités</th><th class="text-end">Intérêts</th><th class="text-end">Capital</th><th>Reçu</th></tr></thead>
                    <tbody>
                        @forelse ($remboursements as $r)
                            <tr>
                                <td>{{ App\Support\Temps\Horloge::affiche($r->created_at) }}</td>
                                <td>{{ $r->mode->libelle() }}</td>
                                <td class="text-end">{{ $r->montant->formate() }}</td>
                                <td class="text-end">{{ $r->penalites->formate() }}</td>
                                <td class="text-end">{{ $r->interets->formate() }}</td>
                                <td class="text-end">{{ $r->capital->formate() }}</td>
                                <td>@if ($r->recu) <a href="{{ route('recus.show', $r->recu) }}">{{ $r->recu->numero }}</a> @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-body-secondary py-3">Aucun remboursement.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $remboursements->links() }}</div>
    @endif
@endsection
