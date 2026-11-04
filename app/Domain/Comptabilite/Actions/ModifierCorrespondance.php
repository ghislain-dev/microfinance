<?php

declare(strict_types=1);

namespace App\Domain\Comptabilite\Actions;

use App\Domain\Comptabilite\Models\CorrespondanceSyscohada;
use App\Domain\Comptabilite\PlanSyscohada;
use App\Models\User;
use App\Support\Acces\AutoriserDansOrganisation;
use InvalidArgumentException;

final class ModifierCorrespondance
{
    public function __construct(private readonly PlanSyscohada $plan) {}

    public function executer(User $comptable, string $rubrique, string $numero, string $libelle): CorrespondanceSyscohada
    {
        AutoriserDansOrganisation::autoriser($comptable, 'parametrer', CorrespondanceSyscohada::class);
        $numero = trim($numero);
        $libelle = trim($libelle);
        if (! array_key_exists($rubrique, PlanSyscohada::PROPOSITION)) {
            throw new InvalidArgumentException("Rubrique inconnue : {$rubrique}.");
        }
        // Classes 1 à 7 du SYSCOHADA, 2 à 8 chiffres.
        if (preg_match('/^[1-7]\d{1,7}$/', $numero) !== 1 || $libelle === '' || mb_strlen($libelle) > 150) {
            throw new InvalidArgumentException('Numéro SYSCOHADA : 2 à 8 chiffres, classe 1 à 7 ; libellé obligatoire (150 caractères au plus).');
        }

        $ligne = CorrespondanceSyscohada::query()->where('rubrique', $rubrique)->first() ?? new CorrespondanceSyscohada;
        $ligne->forceFill(['organisation_id' => $comptable->organisation_id, 'rubrique' => $rubrique, 'numero' => $numero, 'libelle' => $libelle])->save();
        $this->plan->oublier();

        return $ligne;
    }
}
