{{-- Formulaire d'une opération ; $cle évite les doubles envois (idempotence). --}}
<form method="POST" action="{{ $action }}" class="card card-body h-100">
    @csrf
    <input type="hidden" name="cle" value="{{ $cle }}">
    <h2 class="h6">{{ $titre }}</h2>
    @isset($destination)
        <label class="form-label small" for="destination-{{ $id }}">Compte destinataire</label>
        <input id="destination-{{ $id }}" name="destination" value="{{ old('destination') }}" maxlength="60" required
               placeholder="BUK-000046-EPV-1" class="form-control form-control-sm mb-2">
    @endisset
    <label class="form-label small" for="montant-{{ $id }}">Montant ({{ $devise }})</label>
    <input id="montant-{{ $id }}" name="montant" inputmode="decimal" required placeholder="0,00" class="form-control mb-3">
    <button type="submit" class="btn btn-{{ $bouton }} mt-auto">{{ $titre }}</button>
</form>
