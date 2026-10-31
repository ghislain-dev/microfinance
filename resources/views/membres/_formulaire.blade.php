{{-- Champs communs à l'inscription et à la modification. $membre est null à l'inscription. --}}
<div class="row g-3">
    <div class="col-md-4">
        <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
        <input id="nom" name="nom" value="{{ old('nom', $membre?->nom) }}" maxlength="100" required
               @class(['form-control', 'is-invalid' => $errors->has('nom')])>
        @error('nom') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="postnom" class="form-label">Postnom</label>
        <input id="postnom" name="postnom" value="{{ old('postnom', $membre?->postnom) }}" maxlength="100" class="form-control">
    </div>
    <div class="col-md-4">
        <label for="prenom" class="form-label">Prénom</label>
        <input id="prenom" name="prenom" value="{{ old('prenom', $membre?->prenom) }}" maxlength="100" class="form-control">
    </div>

    <div class="col-md-4">
        <label for="sexe" class="form-label">Sexe <span class="text-danger">*</span></label>
        <select id="sexe" name="sexe" required @class(['form-select', 'is-invalid' => $errors->has('sexe')])>
            <option value=""></option>
            @foreach (App\Domain\Membres\Sexe::cases() as $sexe)
                <option value="{{ $sexe->value }}" @selected(old('sexe', $membre?->sexe?->value) === $sexe->value)>{{ $sexe->libelle() }}</option>
            @endforeach
        </select>
        @error('sexe') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="date_naissance" class="form-label">Date de naissance <span class="text-danger">*</span></label>
        <input id="date_naissance" name="date_naissance" type="date" required
               value="{{ old('date_naissance', $membre?->date_naissance?->toDateString()) }}"
               @class(['form-control', 'is-invalid' => $errors->has('date_naissance')])>
        @error('date_naissance') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
        <input id="telephone" name="telephone" type="tel" value="{{ old('telephone', $membre?->telephone) }}" required
               maxlength="30" placeholder="0812345678" @class(['form-control', 'is-invalid' => $errors->has('telephone')])>
        @error('telephone') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @if (session('telephone_partage') || old('confirmer_telephone'))
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="confirmer_telephone" value="1" id="confirmer_telephone"
                       @checked(old('confirmer_telephone'))>
                <label class="form-check-label" for="confirmer_telephone">Je confirme : ce téléphone est partagé</label>
            </div>
        @endif
    </div>

    <div class="col-md-4">
        <label for="piece_type" class="form-label">Type de pièce <span class="text-danger">*</span></label>
        <input id="piece_type" name="piece_type" value="{{ old('piece_type', $membre?->piece_type) }}" maxlength="50" required
               placeholder="Carte d’électeur, passeport…" @class(['form-control', 'is-invalid' => $errors->has('piece_type')])>
        @error('piece_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="piece_numero" class="form-label">Numéro de pièce <span class="text-danger">*</span></label>
        <input id="piece_numero" name="piece_numero" value="{{ old('piece_numero', $membre?->piece_numero) }}" maxlength="50" required
               autocomplete="off" @class(['form-control', 'is-invalid' => $errors->has('piece_numero')])>
        @error('piece_numero') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="adresse" class="form-label">Adresse</label>
        <input id="adresse" name="adresse" value="{{ old('adresse', $membre?->adresse) }}" maxlength="255"
               placeholder="Commune, quartier, avenue, numéro" class="form-control">
    </div>
</div>
