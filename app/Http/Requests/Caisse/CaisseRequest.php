<?php

declare(strict_types=1);

namespace App\Http\Requests\Caisse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CaisseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organisation = $this->user()?->getAttribute('organisation_id');

        return [
            'agence_id' => ['required', 'integer', Rule::exists('agences', 'id')->where('organisation_id', $organisation)->where('actif', true)],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('caisses', 'code')->where('organisation_id', $organisation)],
            'libelle' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Code : lettres majuscules, chiffres et tirets.',
            'code.unique' => 'Ce code de caisse existe déjà.',
            'agence_id.exists' => 'Agence inconnue.',
            'required' => 'Ce champ est obligatoire.',
        ];
    }
}
