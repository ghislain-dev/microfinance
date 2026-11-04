@extends('layouts.app')

@section('titre', 'Collecte')

@section('contenu')
    <h1 class="h3 mb-4">Collecte</h1>

    @if (session('statut'))
        <div class="alert alert-success" role="status">{{ session('statut') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('collecte.parametres') }}" class="card card-body mb-4">
        @csrf
        <h2 class="h6">Paramètres</h2>
        <p class="small text-body-secondary">Sans paramètres, l’application ne permet aucune saisie. Le plafond de retrait ne peut dépasser le seuil de validation 4 yeux.</p>
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="delai_max_heures" class="form-label small">Délai max sans synchronisation (heures)</label>
                <input id="delai_max_heures" name="delai_max_heures" type="number" min="1" max="720" required
                       value="{{ old('delai_max_heures', $parametres?->delai_max_heures) }}" class="form-control">
            </div>
            @foreach (App\Support\Montant\Devise::cases() as $devise)
                @foreach (['plafond_retrait' => 'Retrait max', 'plafond_especes' => 'Espèces max'] as $champ => $libelle)
                    <div class="col-md-2">
                        <label for="{{ $champ }}-{{ $devise->value }}" class="form-label small">{{ $libelle }} ({{ $devise->value }})</label>
                        <input id="{{ $champ }}-{{ $devise->value }}" name="plafonds[{{ $devise->value }}][{{ $champ }}]" inputmode="decimal" required
                               value="{{ old("plafonds.{$devise->value}.{$champ}", isset($plafonds[$devise->value]) ? substr($plafonds[$devise->value]->{$champ}->formate(), 0, -4) : '') }}"
                               @class(['form-control', 'is-invalid' => $errors->has("plafonds.{$devise->value}.{$champ}")])>
                    </div>
                @endforeach
            @endforeach
            <div class="col-auto"><button type="submit" class="btn btn-outline-primary">Enregistrer</button></div>
        </div>
    </form>

    <div class="card mb-4">
        <div class="card-header">Opérations refusées à la synchronisation</div>
        <div class="table-responsive">
            <table class="table mb-0 small">
                <thead><tr><th>Reçue le</th><th>Saisie le</th><th>Type</th><th>Membre</th><th>Raison</th></tr></thead>
                <tbody>
                    @forelse ($rejets as $rejet)
                        <tr>
                            <td>{{ App\Support\Temps\Horloge::affiche($rejet->created_at) }}</td>
                            <td>{{ App\Support\Temps\Horloge::affiche($rejet->getAttribute('saisi_le')) }}</td>
                            <td>{{ $rejet->getAttribute('type') }}</td>
                            <td>{{ $rejet->getAttribute('membre_id') }}</td>
                            <td>{{ $rejet->raison }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">Aucune.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @forelse ($collecteurs as $collecteur)
        <div class="card mb-4">
            <div class="card-header">{{ $collecteur->name }}</div>
            <div class="card-body row g-4">
                <div class="col-lg-5">
                    <h3 class="h6">Téléphones</h3>
                    <ul class="list-group list-group-flush small">
                        @forelse ($appareils[$collecteur->id] ?? [] as $appareil)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>{{ $appareil->modele }} — {{ $appareil->libelleStatut() }}
                                    @if ($appareil->derniere_sync_le) · synchro {{ App\Support\Temps\Horloge::affiche($appareil->derniere_sync_le, 'd/m H:i') }} @endif</span>
                                @if ($appareil->statut !== 'autorise')
                                    <form method="POST" action="{{ route('collecte.appareil', [$appareil, 'autoriser']) }}">@csrf<button type="submit" class="btn btn-success btn-sm">Autoriser</button></form>
                                @else
                                    <form method="POST" action="{{ route('collecte.appareil', [$appareil, 'revoquer']) }}">@csrf<button type="submit" class="btn btn-outline-danger btn-sm">Révoquer</button></form>
                                @endif
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-body-secondary">Aucun téléphone : le collecteur se connecte une première fois depuis l’application.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="col-lg-7">
                    <h3 class="h6">Tournée</h3>
                    <ul class="list-group list-group-flush small mb-2">
                        @forelse ($tournees[$collecteur->id] ?? [] as $tournee)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>{{ $tournee->membre->numero }} — {{ $tournee->membre->nomComplet() }}</span>
                                <form method="POST" action="{{ route('collecte.retirer', [$collecteur, $tournee->membre]) }}">@csrf<button type="submit" class="btn btn-link btn-sm text-danger p-0">Retirer</button></form>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-body-secondary">Aucun membre.</li>
                        @endforelse
                    </ul>
                    <form method="POST" action="{{ route('collecte.affecter', $collecteur) }}" class="d-flex gap-2">
                        @csrf
                        <input name="numero" required maxlength="40" placeholder="N° du membre" aria-label="Numéro du membre à ajouter" class="form-control form-control-sm">
                        <button type="submit" class="btn btn-outline-primary btn-sm">Ajouter</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <p class="text-body-secondary">Aucun utilisateur avec le rôle collecteur.</p>
    @endforelse
@endsection
