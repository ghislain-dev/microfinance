<?php

declare(strict_types=1);

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\ClotureComptable;
use App\Domain\Organisation\Models\Organisation;
use App\Support\Audit\JournalAudit;
use App\Support\Temps\Horloge;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Clôture définitive jusqu'à une date incluse. Appelée seulement par la clôture mensuelle validée (ADR 023).
 */
final class CloturerPeriode
{
    public function __construct(private readonly JournalAudit $journal) {}

    public function executer(Organisation $organisation, CarbonImmutable $dateFin, string $par): ClotureComptable
    {
        if ($dateFin->isAfter(Horloge::aujourdhui($organisation->id))) {
            throw new InvalidArgumentException('Impossible de clôturer une date future.');
        }

        $derniere = ClotureComptable::withoutGlobalScopes()->where('organisation_id', $organisation->id)->max('date_fin');
        if ($derniere !== null && $dateFin->toDateString() <= (string) $derniere) {
            throw new InvalidArgumentException("Déjà clôturé jusqu'au {$derniere}.");
        }

        $cloture = new ClotureComptable(['date_fin' => $dateFin->toDateString(), 'par' => $par]);
        $cloture->organisation_id = $organisation->id;
        $cloture->save();

        $this->journal->enregistrer('clotures.creation', $organisation, apres: ['date_fin' => $dateFin->toDateString(), 'par' => $par]);

        return $cloture;
    }
}
