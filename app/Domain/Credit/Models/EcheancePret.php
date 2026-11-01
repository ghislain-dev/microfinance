<?php

declare(strict_types=1);

namespace App\Domain\Credit\Models;

use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantCast;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Échéance d'un prêt : montants prévus figés au décaissement, montants payés tenus par les remboursements.
 *
 * @property int $rang
 * @property Carbon $date_echeance
 * @property Devise $devise
 * @property Montant $capital
 * @property Montant $interet
 * @property Montant $capital_paye
 * @property Montant $interet_paye
 * @property Montant $penalite
 * @property Montant $penalite_payee
 * @property Carbon|null $payee_le
 */
#[Table('echeances_pret')]
class EcheancePret extends Model
{
    use AppartientAOrganisation;

    /** @var list<string> */
    protected $fillable = [];

    /**
     * @return BelongsTo<Pret, $this>
     */
    public function pret(): BelongsTo
    {
        return $this->belongsTo(Pret::class);
    }

    public function capitalRestant(): Montant
    {
        return $this->capital->moins($this->capital_paye);
    }

    public function interetRestant(): Montant
    {
        return $this->interet->moins($this->interet_paye);
    }

    public function penaliteRestante(): Montant
    {
        return $this->penalite->moins($this->penalite_payee);
    }

    public function resteDu(): Montant
    {
        return $this->capitalRestant()->plus($this->interetRestant())->plus($this->penaliteRestante());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rang' => 'integer',
            'date_echeance' => 'date',
            'devise' => Devise::class,
            'capital' => MontantCast::class.':devise',
            'interet' => MontantCast::class.':devise',
            'capital_paye' => MontantCast::class.':devise',
            'interet_paye' => MontantCast::class.':devise',
            'penalite' => MontantCast::class.':devise',
            'penalite_payee' => MontantCast::class.':devise',
            'payee_le' => 'datetime',
        ];
    }
}
