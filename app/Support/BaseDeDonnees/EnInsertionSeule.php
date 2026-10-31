<?php

declare(strict_types=1);

namespace App\Support\BaseDeDonnees;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle en insertion seule : une ligne enregistrée ne se modifie ni ne se supprime jamais
 * (CLAUDE.md §4.3). La base le garantit aussi (InsertionSeule::proteger dans la migration).
 *
 * @mixin Model
 */
trait EnInsertionSeule
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new ModificationInterdite(static::class.' : insertion seule, correction par extourne.');
        }

        return parent::save($options);
    }

    public function delete(): bool
    {
        throw new ModificationInterdite(static::class.' : insertion seule, correction par extourne.');
    }
}
