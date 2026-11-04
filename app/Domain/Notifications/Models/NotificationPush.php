<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $titre
 * @property string $corps
 * @property array<string, string> $donnees
 * @property Carbon|null $envoye_le
 */
#[Table('notifications_push')]
class NotificationPush extends Model
{
    use AppartientAOrganisation;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'donnees' => 'array', 'envoye_le' => 'datetime'];
    }
}
