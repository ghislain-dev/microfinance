<?php

declare(strict_types=1);

namespace App\Support\Montant;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * Colonne BIGINT de centimes ↔ Montant, la devise étant lue dans une autre colonne du modèle.
 * Usage : 'solde_cache' => MontantCast::class.':devise'
 *
 * @implements CastsAttributes<Montant, Montant>
 */
final class MontantCast implements CastsAttributes
{
    public function __construct(private readonly string $colonneDevise = 'devise') {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Montant
    {
        if ($value === null) {
            return null;
        }

        return new Montant((int) $value, $this->devise($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (! $value instanceof Montant) {
            throw new InvalidArgumentException("{$key} attend un Montant.");
        }

        if ($value->devise !== $this->devise($attributes)) {
            throw new LogicException("{$key} : devise {$value->devise->value} différente de celle du modèle.");
        }

        return [$key => $value->centimes];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function devise(array $attributes): Devise
    {
        $devise = $attributes[$this->colonneDevise] ?? null;

        return $devise instanceof Devise ? $devise : Devise::from((string) $devise);
    }
}
