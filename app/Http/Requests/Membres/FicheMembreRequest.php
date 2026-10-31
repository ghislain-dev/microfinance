<?php

declare(strict_types=1);

namespace App\Http\Requests\Membres;

use App\Domain\Membres\Sexe;
use App\Support\Telephone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fiche membre (inscription et modification). Champs obligatoires validés le 2026-10-31.
 */
class FicheMembreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'postnom' => ['nullable', 'string', 'max:100'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'sexe' => ['required', Rule::enum(Sexe::class)],
            'date_naissance' => ['required', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
            'telephone' => ['required', 'string', 'max:30', function (string $attribut, mixed $valeur, Closure $echec) {
                if (Telephone::normaliser((string) $valeur) === null) {
                    $echec('Numéro de téléphone invalide (ex. 0812345678 ou +243812345678).');
                }
            }],
            'piece_type' => ['required', 'string', 'max:50'],
            'piece_numero' => ['required', 'string', 'max:50'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'confirmer_telephone' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nom' => 'nom', 'sexe' => 'sexe', 'date_naissance' => 'date de naissance', 'telephone' => 'téléphone',
            'piece_type' => 'type de pièce', 'piece_numero' => 'numéro de pièce', 'agence_id' => 'agence',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Le champ :attribute est obligatoire.',
            'date_naissance.before' => 'La date de naissance doit être passée.',
            'date_naissance.date_format' => 'Date de naissance au format AAAA-MM-JJ.',
            'sexe' => 'Sexe : M ou F.',
            'max' => 'Le champ :attribute est trop long.',
        ];
    }

    public function telephonePartageConfirme(): bool
    {
        return $this->boolean('confirmer_telephone');
    }
}
