<?php

declare(strict_types=1);

namespace App\Domain\Change\Models;

use App\Domain\Change\Policies\TauxChangePolicy;
use App\Domain\Change\Taux;
use App\Models\User;
use App\Support\BaseDeDonnees\EnInsertionSeule;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date_application
 */
#[Table('taux_change')]
#[UsePolicy(TauxChangePolicy::class)]
class TauxChange extends Model
{
    use AppartientAOrganisation, EnInsertionSeule;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    public function achat(): Taux
    {
        return new Taux((int) $this->getAttribute('achat'));
    }

    public function vente(): Taux
    {
        return new Taux((int) $this->getAttribute('vente'));
    }

    /**
     * Taux en vigueur : le plus récent saisi aujourd'hui dans l'organisation.
     */
    public static function duJour(): ?self
    {
        return self::query()->whereDate('date_application', today())->latest('id')->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date_application' => 'date'];
    }
}
