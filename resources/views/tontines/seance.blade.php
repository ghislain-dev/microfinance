@extends('layouts.app')

@section('titre', 'Séance '.$seance->rang)

@section('contenu')
    @php($avec = $cycle->groupe->type === App\Domain\Tontines\TypeGroupe::Avec)
    <h1 class="h3 mb-1"><a href="{{ route('cycles.show', $cycle) }}">{{ $cycle->groupe->nom }} — cycle {{ $cycle->numero }}</a> · séance {{ $seance->rang }}</h1>
    <p class="text-body-secondary mb-4">{{ $seance->date_prevue->format('d/m/Y') }} · {{ $seance->statut->libelle() }}
        @if ($tour) · bénéficiaire : {{ $participants->firstWhere('id', $tour->membre_id)?->nomComplet() }} @endif</p>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif
    @error('lignes') <div class="alert alert-danger" role="alert">{{ $message }}</div> @enderror

    @if ($seance->statut === App\Domain\Tontines\StatutSeance::Prevue)
        @can('saisir', $seance)
            <form method="POST" action="{{ url("/seances/{$seance->id}") }}" class="card">
                @csrf
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr><th>Membre</th><th>Présence</th>
                                <th>{{ $avec ? 'Parts (0 à '.$cycle->parts_max.')' : 'Cotisation ('.$cycle->montant_cotisation->formate().')' }}</th>
                                <th>Fonds social ({{ $cycle->cotisation_fonds_social->formate() }})</th><th>Règlement de dette</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($participants as $membre)
                                <tr>
                                    <td>{{ $membre->numero }} — {{ $membre->nomComplet() }}
                                        @if ($dettes[$membre->id]->estPositif()) <div class="small text-danger">Dette : {{ $dettes[$membre->id]->formate() }}</div> @endif</td>
                                    <td>
                                        <select name="lignes[{{ $membre->id }}][presence]" class="form-select form-select-sm" aria-label="Présence">
                                            @foreach (App\Domain\Tontines\Presence::cases() as $presence)
                                                <option value="{{ $presence->value }}" @selected(old("lignes.{$membre->id}.presence") === $presence->value)>{{ $presence->libelle() }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        @if ($avec)
                                            <input type="number" min="0" max="{{ $cycle->parts_max }}" name="lignes[{{ $membre->id }}][parts]" value="{{ old("lignes.{$membre->id}.parts", 1) }}" class="form-control form-control-sm" aria-label="Parts">
                                        @else
                                            <input name="lignes[{{ $membre->id }}][cotisation]" inputmode="decimal" value="{{ old("lignes.{$membre->id}.cotisation", substr($cycle->montant_cotisation->formate(), 0, -4)) }}" class="form-control form-control-sm" aria-label="Cotisation">
                                        @endif
                                    </td>
                                    <td><input name="lignes[{{ $membre->id }}][fonds_social]" inputmode="decimal" value="{{ old("lignes.{$membre->id}.fonds_social", substr($cycle->cotisation_fonds_social->formate(), 0, -4)) }}" class="form-control form-control-sm" aria-label="Fonds social"></td>
                                    <td><input name="lignes[{{ $membre->id }}][reglement]" inputmode="decimal" value="{{ old("lignes.{$membre->id}.reglement", '0') }}" class="form-control form-control-sm" aria-label="Règlement"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($cycle->mode_tours === App\Domain\Tontines\ModeTours::Enchere)
                    <div class="card-body border-top row g-2">
                        <div class="col-md-6">
                            <label for="enchere-membre" class="form-label small">Bénéficiaire (plus forte remise)</label>
                            <select id="enchere-membre" name="enchere[membre_id]" class="form-select">
                                @foreach ($sansTour as $membre) <option value="{{ $membre->id }}">{{ $membre->numero }} — {{ $membre->nomComplet() }}</option> @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="enchere-remise" class="form-label small">Remise</label>
                            <input id="enchere-remise" name="enchere[remise]" inputmode="decimal" value="{{ old('enchere.remise', '0') }}" class="form-control">
                        </div>
                    </div>
                @endif
                <div class="card-footer">
                    <p class="small text-body-secondary mb-2">Montants encaissés dans votre caisse ; impayés, absences et retards deviennent des dettes.</p>
                    <button type="submit" class="btn btn-primary">Enregistrer la séance</button>
                </div>
            </form>
        @endcan
    @else
        <div class="card">
            <table class="table mb-0">
                <thead><tr><th>Membre</th><th>Présence</th><th class="text-end">Encaissé</th></tr></thead>
                <tbody>
                    @foreach ($participants as $membre)
                        <tr>
                            <td>{{ $membre->numero }} — {{ $membre->nomComplet() }}</td>
                            <td>{{ App\Domain\Tontines\Presence::tryFrom((string) ($presences[$membre->id] ?? ''))?->libelle() }}</td>
                            <td class="text-end">{{ (new App\Support\Montant\Montant((int) ($encaissements[$membre->id] ?? collect())->sum('montant'), $cycle->devise))->formate() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
