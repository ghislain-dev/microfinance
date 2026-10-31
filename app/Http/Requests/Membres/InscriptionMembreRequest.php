<?php

declare(strict_types=1);

namespace App\Http\Requests\Membres;

use Illuminate\Validation\Rule;

class InscriptionMembreRequest extends FicheMembreRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // Agence de l'organisation de l'utilisateur connecté, jamais d'une autre.
            'agence_id' => ['required', 'integer', Rule::exists('agences', 'id')
                ->where('organisation_id', $this->user()?->getAttribute('organisation_id'))
                ->where('actif', true)],
        ];
    }
}
