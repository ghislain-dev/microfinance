@extends('layouts.app')

@section('titre', 'Tableau de bord')

@section('contenu')
    <h1 class="h3 mb-4">Tableau de bord</h1>

    <div class="row g-4">
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 text-body-secondary">Connecté en tant que</h2>
                    <p class="fs-5 mb-1">{{ $utilisateur->name }}</p>
                    <p class="mb-0">
                        @foreach ($utilisateur->getRoleNames() as $role)
                            <span class="badge text-bg-secondary">{{ App\Domain\Organisation\Acces\Role::tryFrom($role)?->libelle() ?? $role }}</span>
                        @endforeach
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 text-body-secondary">Organisation</h2>
                    <p class="fs-5 mb-1">{{ $utilisateur->organisation?->nom }}</p>
                    <p class="mb-0 small">
                        Agences :
                        @forelse ($utilisateur->agences as $agence)
                            <span class="badge text-bg-light border">{{ $agence->nom }}</span>
                        @empty
                            <span class="text-body-secondary">aucune</span>
                        @endforelse
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        @foreach ($blocs as $titre => $lignes)
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h6">{{ $titre }}</h2>
                        <ul class="list-unstyled small mb-0">
                            @foreach ($lignes as $ligne) <li>{{ $ligne }}</li> @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
