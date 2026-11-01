<?php

declare(strict_types=1);

namespace App\Http\Requests\Tontines;

use App\Domain\Tontines\ModeTours;
use App\Domain\Tontines\Periodicite;
use App\Support\Montant\Devise;
use App\Support\Montant\MontantSaisi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CycleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $zero = new MontantSaisi(zeroAutorise: true);

        return [
            'devise' => ['required', Rule::enum(Devise::class)],
            'periodicite' => ['required', Rule::enum(Periodicite::class)],
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'montant_cotisation' => ['required', new MontantSaisi],
            'cotisation_fonds_social' => ['required', $zero],
            'penalite_absence' => ['required', $zero],
            'penalite_retard' => ['required', $zero],
            'penalite_impaye' => ['required', $zero],
            'mode_tours' => ['nullable', Rule::enum(ModeTours::class)],
            'nombre_seances' => ['nullable', 'integer', 'min:1', 'max:520'],
        ];
    }

    /**
     * Données prêtes pour CreerCycle (champs vides → null).
     *
     * @return array<string, mixed>
     */
    public function parametres(): array
    {
        $donnees = $this->validated();
        $donnees['mode_tours'] = ($donnees['mode_tours'] ?? '') === '' ? null : $donnees['mode_tours'];
        $donnees['nombre_seances'] = ($donnees['nombre_seances'] ?? '') === '' ? null : (int) $donnees['nombre_seances'];

        return $donnees;
    }
}
