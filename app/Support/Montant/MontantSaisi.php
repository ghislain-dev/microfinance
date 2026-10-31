<?php

declare(strict_types=1);

namespace App\Support\Montant;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Règle des Form Requests : un montant saisi strictement positif, au plus 2 décimales.
 */
final class MontantSaisi implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $valide = is_string($value) && Montant::depuisSaisie($value, Devise::USD)->estPositif();
        } catch (MontantInvalide) {
            $valide = false;
        }

        if (! $valide) {
            $fail('Le montant doit être positif, avec au plus 2 décimales (ex. 1250,50).');
        }
    }
}
