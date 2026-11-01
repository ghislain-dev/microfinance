@extends('layouts.app')

@section('titre', $cycle->groupe->code.' — cycle '.$cycle->numero)

@section('contenu')
    <h1 class="h3 mb-1"><a href="{{ route('tontines.show', $cycle->groupe) }}">{{ $cycle->groupe->nom }}</a> — cycle {{ $cycle->numero }}</h1>
    <p class="text-body-secondary mb-4">
        {{ $cycle->statut->libelle() }} · {{ $cycle->groupe->type->libelle() }} · {{ $cycle->periodicite->libelle() }} dès le {{ $cycle->date_debut->format('d/m/Y') }} ·
        {{ $cycle->groupe->type === App\Domain\Tontines\TypeGroupe::Avec ? 'part' : 'cotisation' }} {{ $cycle->montant_cotisation->formate() }} ·
        fonds social {{ $cycle->cotisation_fonds_social->formate() }}
        @if ($cycle->mode_tours) · {{ $cycle->mode_tours->libelle() }} @endif
    </p>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($cycle->statut === App\Domain\Tontines\StatutCycle::EnPreparation)
        @can('update', $cycle)
            <form method="POST" action="{{ route('cycles.demarrer', $cycle) }}" class="card card-body mb-4">
                @csrf
                <h2 class="h6">Démarrer le cycle</h2>
                <p class="small text-body-secondary">Les membres actifs du groupe deviennent les participants ; les paramètres sont alors figés.</p>
                @if ($cycle->mode_tours === App\Domain\Tontines\ModeTours::OrdreFixe)
                    <div class="row g-2 mb-3">
                        @foreach ($membresDuGroupe as $i => $membre)
                            <div class="col-md-4">
                                <label for="ordre-{{ $i + 1 }}" class="form-label small">Tour {{ $i + 1 }}</label>
                                <select id="ordre-{{ $i + 1 }}" name="ordre[{{ $i + 1 }}]" class="form-select">
                                    @foreach ($membresDuGroupe as $choix)
                                        <option value="{{ $choix->id }}" @selected($choix->id === $membre->id)>{{ $choix->numero }} — {{ $choix->nom }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div><button type="submit" class="btn btn-primary">Démarrer</button></div>
            </form>
        @endcan
    @else
        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="card card-body"><div class="small text-body-secondary">Fonds du cycle</div><div class="fs-5">{{ $soldeFonds?->formate() }}</div></div></div>
            <div class="col-md-4"><div class="card card-body"><div class="small text-body-secondary">Fonds social</div><div class="fs-5">{{ $soldeFondsSocial?->formate() }}</div></div></div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">Séances</div>
                    <table class="table mb-0">
                        <thead><tr><th>N°</th><th>Date</th><th>Bénéficiaire</th><th>Statut</th></tr></thead>
                        <tbody>
                            @foreach ($seances as $seance)
                                <tr>
                                    <td><a href="{{ route('seances.show', $seance) }}">{{ $seance->rang }}</a></td>
                                    <td>{{ $seance->date_prevue->format('d/m/Y') }}</td>
                                    <td>{{ $participants->firstWhere('id', $tours[$seance->rang]->membre_id ?? null)?->numero ?? '—' }}</td>
                                    <td>{{ $seance->statut->libelle() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">Participants et dettes</div>
                    <table class="table mb-0">
                        <thead><tr><th>Membre</th><th class="text-end">Dette</th></tr></thead>
                        <tbody>
                            @foreach ($participants as $membre)
                                <tr><td>{{ $membre->numero }} — {{ $membre->nomComplet() }}</td><td class="text-end">{{ $dettes[$membre->id]->formate() }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Aides du fonds social</div>
            <ul class="list-group list-group-flush">
                @forelse ($aides as $aide)
                    <li class="list-group-item">
                        {{ $aide->membre->numero }} — {{ (new App\Support\Montant\Montant($aide->montant, $cycle->devise))->formate() }} — {{ $aide->motif }}
                        <span class="badge text-bg-light border">{{ ['en_attente' => 'En attente du comité', 'validee' => 'Versée', 'rejetee' => 'Rejetée'][$aide->statut] }}</span>
                        @can('decider', $aide)
                            <div class="row g-2 mt-1">
                                @foreach (['valider' => ['Valider', 'success'], 'rejeter' => ['Rejeter', 'outline-danger']] as $decision => [$libelle, $couleur])
                                    <form method="POST" action="{{ route('aides.decider', [$aide, $decision]) }}" class="col-md-6 d-flex gap-2">
                                        @csrf
                                        <input name="motif" required maxlength="500" placeholder="Motif" class="form-control form-control-sm" aria-label="Motif">
                                        <button type="submit" class="btn btn-{{ $couleur }} btn-sm">{{ $libelle }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @endcan
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary">Aucune aide.</li>
                @endforelse
            </ul>
            @if ($cycle->statut === App\Domain\Tontines\StatutCycle::EnCours)
                @can('update', $cycle)
                    <form method="POST" action="{{ route('cycles.aides', $cycle) }}" class="card-footer row g-2 m-0">
                        @csrf
                        <div class="col-md-4">
                            <select name="membre_id" class="form-select form-select-sm" aria-label="Membre aidé">
                                @foreach ($participants as $membre) <option value="{{ $membre->id }}">{{ $membre->numero }} — {{ $membre->nom }}</option> @endforeach
                            </select>
                        </div>
                        <div class="col-md-2"><input name="montant" inputmode="decimal" required placeholder="Montant" class="form-control form-control-sm" aria-label="Montant"></div>
                        <div class="col-md-4"><input name="motif" required maxlength="500" placeholder="Motif (deuil, maladie…)" class="form-control form-control-sm" aria-label="Motif"></div>
                        <div class="col-md-2"><button type="submit" class="btn btn-outline-primary btn-sm w-100">Demander</button></div>
                    </form>
                @endcan
            @endif
        </div>

        <div class="card">
            <div class="card-header">Partage de fin de cycle</div>
            @if ($partage)
                <div class="card-body pb-0">
                    <p>Total {{ $partage->montant_total->formate() }} — {{ ['brouillon' => 'à valider par le comité', 'valide' => 'versé', 'rejete' => 'rejeté'][$partage->statut] }}</p>
                </div>
                <table class="table mb-0">
                    <thead><tr><th>Membre</th>@if ($cycle->groupe->type === App\Domain\Tontines\TypeGroupe::Avec)<th class="text-end">Parts</th>@endif<th class="text-end">Part</th><th class="text-end">Dette réglée</th><th class="text-end">Versé</th></tr></thead>
                    <tbody>
                        @foreach ($lignesPartage as $ligne)
                            <tr>
                                <td>{{ $ligne->membre->numero }} — {{ $ligne->membre->nomComplet() }}</td>
                                @if ($cycle->groupe->type === App\Domain\Tontines\TypeGroupe::Avec)<td class="text-end">{{ $ligne->parts }}</td>@endif
                                <td class="text-end">{{ $ligne->part->formate() }}</td>
                                <td class="text-end">{{ $ligne->compensation->formate() }}</td>
                                <td class="text-end">{{ $ligne->verse->formate() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @can('decider', $partage)
                    <div class="card-footer row g-2 m-0">
                        @foreach (['valider' => ['Valider et verser', 'success'], 'rejeter' => ['Rejeter', 'outline-danger']] as $decision => [$libelle, $couleur])
                            <form method="POST" action="{{ route('partages.decider', [$partage, $decision]) }}" class="col-md-6 d-flex gap-2">
                                @csrf
                                <input name="motif" required maxlength="500" placeholder="Motif" class="form-control" aria-label="Motif">
                                <button type="submit" class="btn btn-{{ $couleur }}">{{ $libelle }}</button>
                            </form>
                        @endforeach
                    </div>
                @endcan
            @elseif ($cycle->statut === App\Domain\Tontines\StatutCycle::EnCours)
                @can('update', $cycle)
                    <form method="POST" action="{{ route('cycles.partage', $cycle) }}" class="card-body">
                        @csrf
                        <p class="small text-body-secondary">Après la dernière séance : le fonds du cycle est réparti, les dettes sont déduites des parts.</p>
                        <button type="submit" class="btn btn-outline-primary">Calculer le partage</button>
                    </form>
                @endcan
            @endif
        </div>
    @endif
@endsection
