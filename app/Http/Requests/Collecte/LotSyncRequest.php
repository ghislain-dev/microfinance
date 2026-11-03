<?php

declare(strict_types=1);

namespace App\Http\Requests\Collecte;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Lot d'opérations saisies hors-ligne. Montants en centimes (entiers) ; le serveur relit comptes, devises et
 * soldes en base (CLAUDE.md §9) : l'opération qui ne respecte pas une règle est rejetée seule, pas le lot.
 */
class LotSyncRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lot_uuid' => ['required', 'uuid'],
            'operations' => ['required', 'array', 'min:1', 'max:500'],
            'operations.*.cle' => ['required', 'uuid', 'distinct'],
            'operations.*.type' => ['required', 'string', 'max:20'],
            'operations.*.membre_id' => ['nullable', 'integer'],
            'operations.*.compte_epargne_id' => ['nullable', 'integer'],
            'operations.*.pret_id' => ['nullable', 'integer'],
            'operations.*.cycle_id' => ['nullable', 'integer'],
            'operations.*.montant_centimes' => ['nullable', 'integer'],
            'operations.*.fonds_social_centimes' => ['nullable', 'integer'],
            'operations.*.parts' => ['nullable', 'integer'],
            'operations.*.saisi_le' => ['nullable', 'date'],
        ];
    }

    /**
     * Opérations dans l'ordre de saisie au téléphone : un dépôt collecté avant un retrait paie ce retrait.
     *
     * @return list<array<string, mixed>>
     */
    public function operationsParOrdreDeSaisie(): array
    {
        $operations = array_values(array_filter((array) $this->validated('operations'), 'is_array'));
        $moment = fn (array $op): int => isset($op['saisi_le']) ? CarbonImmutable::parse((string) $op['saisi_le'])->getTimestamp() : PHP_INT_MAX;
        usort($operations, fn (array $a, array $b) => $moment($a) <=> $moment($b));

        return $operations;
    }
}
