<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ligne du journal d'audit, en insertion seule (code + triggers en base).
 * Écrire uniquement via JournalAudit::enregistrer().
 *
 * @property array<string, mixed>|null $avant
 * @property array<string, mixed>|null $apres
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'action', 'cible_type', 'cible_id', 'avant', 'apres', 'ip', 'appareil'])]
#[Table('audit_logs')]
class LigneAudit extends Model
{
    use AppartientAOrganisation;

    public const UPDATED_AT = null;

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LigneAuditImmuable('Une ligne d’audit ne se modifie jamais.');
        }

        return parent::save($options);
    }

    public function delete(): bool
    {
        throw new LigneAuditImmuable('Une ligne d’audit ne se supprime jamais.');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'avant' => 'array',
            'apres' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
