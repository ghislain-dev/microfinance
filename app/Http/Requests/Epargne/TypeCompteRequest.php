<?php

declare(strict_types=1);

namespace App\Http\Requests\Epargne;

use App\Support\Montant\Devise;
use App\Support\Montant\MontantSaisi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TypeCompteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creation = $this->isMethod('post');

        return [
            // Code et devise : fixés à la création, ignorés ensuite.
            'code' => $creation ? ['required', 'string', 'regex:/^[A-Z0-9]{2,10}$/',
                Rule::unique('types_comptes_epargne', 'code')->where('organisation_id', $this->user()?->getAttribute('organisation_id'))] : [],
            'devise' => $creation ? ['required', Rule::enum(Devise::class)] : [],
            'libelle' => ['required', 'string', 'max:100'],
            'solde_minimum' => ['required', new MontantSaisi(zeroAutorise: true)],
            'depot_minimum' => ['required', new MontantSaisi(zeroAutorise: true)],
            'frais_retrait' => ['required', new MontantSaisi(zeroAutorise: true)],
            'duree_blocage_mois' => ['nullable', 'integer', 'min:1', 'max:600'],
            'actif' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Code : 2 à 10 lettres majuscules ou chiffres (ex. EPV, DAT).',
            'code.unique' => 'Ce code existe déjà.',
            'devise' => 'Devise : USD ou CDF.',
            'duree_blocage_mois.min' => 'Durée de blocage : au moins 1 mois (laisser vide si retraits libres).',
            'required' => 'Ce champ est obligatoire.',
        ];
    }
}
