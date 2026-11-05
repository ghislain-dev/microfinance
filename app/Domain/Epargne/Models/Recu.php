<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Models;

use App\Domain\Credit\Models\Remboursement;
use App\Domain\Epargne\Policies\RecuPolicy;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Devise $devise
 * @property Montant $solde_apres
 * @property string|null $chemin
 */
#[Table('recus')]
#[UsePolicy(RecuPolicy::class)]
class Recu extends Model
{
    use AppartientAOrganisation;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return BelongsTo<MouvementEpargne, $this>
     */
    public function mouvement(): BelongsTo
    {
        return $this->belongsTo(MouvementEpargne::class, 'mouvement_epargne_id');
    }

    /**
     * @return BelongsTo<Remboursement, $this>
     */
    public function remboursement(): BelongsTo
    {
        return $this->belongsTo(Remboursement::class, 'remboursement_pret_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['devise' => Devise::class, 'solde_apres' => MontantCast::class.':devise'];
    }
}
