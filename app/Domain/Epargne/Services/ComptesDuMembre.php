<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Services;

use App\Domain\Epargne\Models\CompteEpargne;
use App\Domain\Finance\MoteurFinancier;
use App\Domain\Membres\Models\Membre;
use App\Support\Montant\Montant;

/**
 * Comptes d'épargne d'un membre avec leur solde réel (somme des écritures du grand livre).
 */
final class ComptesDuMembre
{
    public function __construct(private readonly MoteurFinancier $moteur) {}

    /**
     * @return list<array{compte: CompteEpargne, solde: Montant}>
     */
    public function pour(Membre $membre): array
    {
        return CompteEpargne::query()->with(['type:id,code,libelle', 'compte'])
            ->where('membre_id', $membre->id)->orderBy('id')->get()
            ->map(fn (CompteEpargne $c) => ['compte' => $c, 'solde' => $this->moteur->soldeReel($c->compte)])
            ->values()->all();
    }
}
