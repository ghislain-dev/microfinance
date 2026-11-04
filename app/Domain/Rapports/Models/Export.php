<?php

declare(strict_types=1);

namespace App\Domain\Rapports\Models;

use App\Domain\Rapports\Policies\ExportPolicy;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $type
 * @property string $format
 * @property array<string, mixed> $parametres
 * @property string $statut
 * @property int $user_id
 */
#[Table('exports')]
#[UsePolicy(ExportPolicy::class)]
class Export extends Model
{
    use AppartientAOrganisation;

    public const TYPES = [
        'balance' => 'Balance',
        'grand_livre' => 'Grand livre',
        'journal' => 'Journal',
        'releve' => 'Relevé de compte membre',
        'portefeuille' => 'Portefeuille de crédit',
        'caisse' => 'Caisse et collecte',
    ];

    /** @var list<string> */
    protected $fillable = [];

    public function libelle(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['parametres' => 'array', 'user_id' => 'integer'];
    }
}
