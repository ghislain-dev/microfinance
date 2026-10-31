<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $recu->numero }}</title>
    <style>
        @page { size: A5; margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #222; }
        .entete { border-bottom: 1px solid #999; padding-bottom: 6px; margin-bottom: 10px; }
        .entete img { max-height: 40px; max-width: 120px; float: right; }
        h1 { font-size: 14pt; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 0; vertical-align: top; }
        td.libelle { width: 40%; color: #555; }
        .montant { font-size: 13pt; font-weight: bold; }
        .lettres { font-style: italic; }
        /* Espace pour signer au-dessus de la ligne, libellé juste en dessous. */
        .signatures { margin-top: 55px; }
        .signatures td { width: 47%; padding-top: 3px; border-top: 1px solid #555; text-align: center; font-size: 9pt; }
        .espace { width: 6%; border: 0 !important; }
    </style>
</head>
<body>
    <div class="entete">
        @if ($logo) <img src="{{ $logo }}" alt="Logo"> @endif
        <h1>{{ $organisation?->nom }}</h1>
        <div>Agence {{ $compte->membre->agence?->code }} — {{ $compte->membre->agence?->nom }}</div>
    </div>

    <table>
        <tr><td class="libelle">Reçu n°</td><td><strong>{{ $recu->numero }}</strong></td></tr>
        <tr><td class="libelle">Date</td><td>{{ $mouvement->created_at?->format('d/m/Y H:i') }}</td></tr>
        <tr><td class="libelle">Opération</td><td>{{ $mouvement->libelleType() }}</td></tr>
        <tr><td class="libelle">Membre</td><td>{{ $compte->membre->nomComplet() }} ({{ $compte->membre->numero }})</td></tr>
        <tr><td class="libelle">Compte</td><td>{{ $compte->numero }} — {{ $compte->type->libelle }}</td></tr>
        <tr><td class="libelle">Montant</td><td class="montant">{{ $mouvement->montant->formate() }}</td></tr>
        <tr><td class="libelle">En lettres</td><td class="lettres">{{ $enLettres }}</td></tr>
        @unless ($mouvement->frais->estZero())
            <tr><td class="libelle">Frais</td><td>{{ $mouvement->frais->formate() }}</td></tr>
        @endunless
        <tr><td class="libelle">Solde après opération</td><td>{{ $recu->solde_apres->formate() }}</td></tr>
        <tr><td class="libelle">Caissier</td><td>{{ $caissier?->name ?? '—' }}</td></tr>
    </table>

    <table class="signatures">
        <tr><td>Signature du membre</td><td class="espace"></td><td>Signature du caissier</td></tr>
    </table>
</body>
</html>
