<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand-lg bg-primary" data-bs-theme="dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('tableau-de-bord') }}">{{ config('app.name') }}</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu-principal"
                    aria-controls="menu-principal" aria-expanded="false" aria-label="Ouvrir le menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menu-principal">
                <ul class="navbar-nav me-auto">
                    @foreach ($menu as $entree)
                        <li class="nav-item">
                            @if ($entree->route)
                                <a @class(['nav-link', 'active' => $entree->estActive()]) href="{{ route($entree->route) }}"
                                   @if ($entree->estActive()) aria-current="page" @endif>{{ $entree->libelle }}</a>
                            @else
                                <span class="nav-link disabled" aria-disabled="true">
                                    {{ $entree->libelle }} <span class="badge text-bg-light">bientôt</span>
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <span class="navbar-text me-3">{{ $utilisateur->organisation?->nom }}</span>
                <form method="POST" action="{{ route('deconnexion') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Se déconnecter</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        @yield('contenu')
    </main>
</body>
</html>
