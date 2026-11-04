<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $tableau->titre }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .sous-titre { color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #ddd; padding: 3px 4px; text-align: left; vertical-align: top; }
        th { background: #f2f2f2; }
        .droite { text-align: right; white-space: nowrap; }
        .pied { margin-top: 8px; color: #777; font-size: 8px; }
    </style>
</head>
<body>
    <div>{{ $organisation->nom }}</div>
    <h1>{{ $tableau->titre }}</h1>
    <div class="sous-titre">{{ $tableau->sousTitre }}</div>
    <table>
        <thead>
            <tr>
                @foreach ($tableau->colonnes as $i => $colonne)
                    <th @class(['droite' => in_array($i, $tableau->aDroite, true)])>{{ $colonne }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($tableau->lignes as $ligne)
                <tr>
                    @foreach ($ligne as $i => $cellule)
                        <td @class(['droite' => in_array($i, $tableau->aDroite, true)])>{{ $cellule }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="pied">Édité le {{ now()->format('d/m/Y à H:i') }}</div>
</body>
</html>
