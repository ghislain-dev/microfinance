<?php

declare(strict_types=1);

namespace App\Http\Resources\Membre;

use App\Domain\Credit\Models\EcheancePret;
use App\Domain\Credit\Models\Pret;
use App\Http\Resources\MontantJson;
use App\Support\Montant\Montant;
use App\Support\Montant\Pourcentage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pret
 */
class PretMembreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $echeances = $this->echeances;

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'montant' => MontantJson::de($this->montant),
            'taux_mensuel' => Pourcentage::formate($this->taux_mensuel_pb),
            'date_decaissement' => $this->date_decaissement?->toDateString(),
            'reste_du' => MontantJson::de($echeances->reduce(fn (Montant $t, EcheancePret $e) => $t->plus($e->resteDu()), Montant::zero($this->devise))),
            'echeances' => $echeances->map(fn (EcheancePret $e) => [
                'rang' => $e->rang,
                'date' => $e->date_echeance->toDateString(),
                'capital' => MontantJson::de($e->capital),
                'interet' => MontantJson::de($e->interet),
                'penalite' => MontantJson::de($e->penalite),
                'paye' => MontantJson::de($e->paye()),
                'reste_du' => MontantJson::de($e->resteDu()),
                'payee' => $e->payee_le !== null,
            ])->values()->all(),
        ];
    }
}
