<?php

declare(strict_types=1);

namespace App\Domain\Finance\Models;

use App\Domain\Finance\Comptes\NatureCompte;
use App\Domain\Organisation\Models\Agence;
use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compte du grand livre. Son solde fait foi uniquement comme somme des écritures (CLAUDE.md §4.4) ;
 * solde_cache n'est écrit que par le MoteurFinancier et vérifié chaque nuit.
 *
 * @property NatureCompte $nature
 * @property Devise $devise
 * @property Montant $solde_cache
 */
#[Fillable(['numero', 'libelle', 'nature', 'devise', 'syscohada', 'actif'])]
class Compte extends Model
{
    use AppartientAOrganisation;

    /**
     * @return BelongsTo<Agence, $this>
     */
    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nature' => NatureCompte::class,
            'devise' => Devise::class,
            'solde_cache' => MontantCast::class.':devise',
            'actif' => 'boolean',
        ];
    }
}
