<?php

declare(strict_types=1);

namespace App\Http\Requests\Credit;

use App\Domain\Credit\Periodicite;
use App\Support\Montant\Devise;
use App\Support\Montant\MontantSaisi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProduitCreditRequest extends FormRequest
{
    private const POURCENTAGE = 'regex:/^\d{1,3}([.,]\d{1,2})?$/';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creation = $this->isMethod('post');

        return [
            // Code et devise : fixés à la création, ignorés ensuite.
            'code' => $creation ? ['required', 'string', 'regex:/^[A-Z0-9]{2,10}$/',
                Rule::unique('produits_credit', 'code')->where('organisation_id', $this->user()?->getAttribute('organisation_id'))] : [],
            'devise' => $creation ? ['required', Rule::enum(Devise::class)] : [],
            'libelle' => ['required', 'string', 'max:100'],
            'taux_mensuel' => ['required', 'string', self::POURCENTAGE],
            'periodicite' => ['required', Rule::enum(Periodicite::class)],
            'duree_max' => ['required', 'integer', 'min:1', 'max:520'],
            'montant_minimum' => ['required', new MontantSaisi],
            'montant_maximum' => ['required', new MontantSaisi],
            'anciennete_mois' => ['required', 'integer', 'min:0', 'max:600'],
            'multiple_epargne' => ['required', 'integer', 'min:1', 'max:100'],
            'garantie_epargne' => ['required', 'string', 'regex:/^(100([.,]0{1,2})?|\d{1,2}([.,]\d{1,2})?)$/'],
            'penalite_jour' => ['required', 'string', self::POURCENTAGE],
            'delai_grace_jours' => ['required', 'integer', 'min:0', 'max:365'],
            'delai_saisie_garantie_jours' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'actif' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Code : 2 à 10 lettres majuscules ou chiffres (ex. PME, AGR).',
            'code.unique' => 'Ce code existe déjà.',
            'taux_mensuel.regex' => 'Taux mensuel en %, deux décimales au plus (ex. 2,5).',
            'garantie_epargne.regex' => 'Garantie : de 0 à 100 %, deux décimales au plus.',
            'penalite_jour.regex' => 'Pénalité journalière en %, deux décimales au plus (ex. 0,5).',
            'required' => 'Ce champ est obligatoire.',
        ];
    }
}
