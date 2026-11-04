{{-- Filtre commun aux états : devise et période (dates de valeur). --}}
<form method="GET" class="row g-2 align-items-end mb-4">
    @foreach ($caches ?? [] as $nom => $valeur)
        <input type="hidden" name="{{ $nom }}" value="{{ $valeur }}">
    @endforeach
    <div class="col-md-2">
        <label for="devise" class="form-label small">Devise</label>
        <select id="devise" name="devise" class="form-select">
            @foreach (App\Support\Montant\Devise::cases() as $d)
                <option value="{{ $d->value }}" @selected($devise === $d)>{{ $d->value }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label for="du" class="form-label small">Du</label>
        <input id="du" type="date" name="du" value="{{ $du->toDateString() }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label for="au" class="form-label small">Au</label>
        <input id="au" type="date" name="au" value="{{ $au->toDateString() }}" class="form-control">
    </div>
    <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Afficher</button></div>
    @if ($errors->any()) <div class="col-12 text-danger small">{{ $errors->first() }}</div> @endif
</form>
