<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary d-flex align-items-center">
    <main class="container py-5">
        <div class="card shadow-sm mx-auto carte-connexion">
            <div class="card-body p-4">
                <p class="text-body-secondary small mb-1">{{ config('app.name') }}</p>
                @yield('contenu')
            </div>
        </div>
    </main>
</body>
</html>
