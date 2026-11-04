<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Actions;

use App\Domain\Comptabilite\ClotureRefusee;
use App\Domain\Comptabilite\Models\DemandeCloture;
use App\Domain\Comptabilite\Services\ControlesCloture;
use App\Domain\Finance\Models\ClotureComptable;
use App\Domain\Organisation\Models\Organisation;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Temps\Horloge;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Le comptable demande la clôture d'un mois terminé, après les contrôles bloquants (ADR 023).
 */
final class DemanderCloture
{
    public function __construct(private readonly ControlesCloture $controles) {}

    public function executer(User $comptable, CarbonImmutable $mois): DemandeCloture
    {
        AutoriserDansOrganisation::autoriser($comptable, 'cloturer', DemandeCloture::class);
        $fin = $mois->endOfMonth()->startOfDay();
        if (! $fin->isBefore(Horloge::aujourdhui($comptable->organisation_id))) {
            throw new ClotureRefusee('Seul un mois terminé peut être clôturé.');
        }
        $derniere = ClotureComptable::query()->max('date_fin');
        if ($derniere !== null && $fin->toDateString() <= (string) $derniere) {
            throw new ClotureRefusee("Déjà clôturé jusqu'au ".CarbonImmutable::parse((string) $derniere)->format('d/m/Y').'.');
        }
        $problemes = $this->controles->verifier(Organisation::query()->findOrFail($comptable->organisation_id), $fin);
        if ($problemes !== []) {
            throw new ClotureRefusee('Clôture impossible :', $problemes);
        }

        $demande = new DemandeCloture;
        try {
            $demande->forceFill([
                'organisation_id' => $comptable->organisation_id, 'date_fin' => $fin->toDateString(),
                'statut' => 'en_attente', 'demande_par_id' => $comptable->id,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            throw new ClotureRefusee('Une demande de clôture attend déjà la validation du gérant.');
        }

        return $demande;
    }
}
