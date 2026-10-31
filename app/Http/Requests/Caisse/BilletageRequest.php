<?php

declare(strict_types=1);

namespace App\Http\Requests\Caisse;

use App\Domain\Caisse\Billetage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class BilletageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['billetage' => ['nullable', 'array']];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            try {
                $this->billetage();
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('billetage', $e->getMessage());
            }
        }];
    }

    public function billetage(): Billetage
    {
        // Champs vides du formulaire = 0 billet.
        $saisie = array_map(
            fn ($lignes) => array_map(fn ($n) => $n === null || $n === '' ? 0 : $n, (array) $lignes),
            (array) $this->input('billetage', []),
        );

        return Billetage::depuisSaisie($saisie);
    }
}
