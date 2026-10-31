<?php

declare(strict_types=1);

namespace App\Domain\Epargne\Remuneration\Actions;

use App\Domain\Epargne\Remuneration\Lots;
use App\Domain\Epargne\Remuneration\Models\Remuneration;
use App\Domain\Organisation\Models\Organisation;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use App\Support\Montant\Montant;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Ristourne décidée par l'assemblée : montant réparti au prorata des soldes à la date de référence,
 * méthode du plus fort reste (Σ des parts = montant, au centime). Lot à faire valider par une autre personne.
 */
final class CalculerRistourne
{
    public function __construct(private readonly Lots $lots) {}

    public function executer(User $acteur, Montant $total, CarbonInterface $dateReference): Remuneration
    {
        AutoriserDansOrganisation::autoriser($acteur, 'create', Remuneration::class);
        if (! $total->estPositif()) {
            throw new InvalidArgumentException('Le montant de la ristourne doit être positif.');
        }
        if ($dateReference->isAfter(today())) {
            throw new InvalidArgumentException('La date de référence ne peut pas être dans le futur.');
        }

        $organisation = Organisation::query()->findOrFail($acteur->organisation_id);
        $eligibles = $this->lots->eligibles($organisation, $total->devise, $dateReference);
        $somme = array_reduce($eligibles, fn (int $s, array $l) => $s + $l['base']->centimes, 0);
        if ($somme === 0) {
            throw new InvalidArgumentException('Aucune épargne éligible à la date de référence.');
        }

        // Part entière (au centime inférieur) puis restes, distribués un centime à la fois aux plus forts restes.
        $parts = [];
        foreach ($eligibles as $i => $l) {
            $produit = BigInteger::of($total->centimes)->multipliedBy($l['base']->centimes);
            $parts[$i] = ['part' => $produit->dividedBy($somme, RoundingMode::Down)->toInt(), 'reste' => $produit->mod($somme)->toInt()];
        }
        $ordre = array_keys($parts);
        usort($ordre, fn (int $a, int $b) => [$parts[$b]['reste'], $eligibles[$b]['base']->centimes, $eligibles[$a]['compte']->id]
            <=> [$parts[$a]['reste'], $eligibles[$a]['base']->centimes, $eligibles[$b]['compte']->id]);
        $aDistribuer = $total->centimes - array_sum(array_column($parts, 'part'));
        foreach (array_slice($ordre, 0, $aDistribuer) as $i) {
            $parts[$i]['part']++;
        }

        $lignes = array_map(fn (int $i) => [...$eligibles[$i], 'montant' => new Montant($parts[$i]['part'], $total->devise)], array_keys($eligibles));

        return $this->lots->creer($organisation, 'ristourne', $total->devise, $dateReference->toDateString(), $dateReference, $lignes);
    }
}
