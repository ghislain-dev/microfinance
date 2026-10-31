<?php

declare(strict_types=1);

namespace App\Support\Montant;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Règle des Form Requests : un montant saisi strictement positif (ou nul si autorisé), au plus 2 décimales.
 */
final class MontantSaisi implements ValidationRule
{
    public function __construct(private readonly bool $zeroAutorise = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $montant = is_string($value) ? Montant::depuisSaisie($value, Devise::USD) : null;
            $valide = $montant !== null && ($montant->estPositif() || ($this->zeroAutorise && $montant->estZero()));
        } catch (MontantInvalide) {
            $valide = false;
        }

        if (! $valide) {
            $fail($this->zeroAutorise
                ? 'Montant positif ou nul, avec au plus 2 décimales (ex. 1250,50).'
                : 'Le montant doit être positif, avec au plus 2 décimales (ex. 1250,50).');
        }
    }
}
