<?php

declare(strict_types=1);

namespace App\Http\Requests\Credit;

use App\Domain\Credit\MethodeCalcul;
use App\Support\Montant\MontantSaisi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Demande de prêt : chaque champ des garanties est validé (un champ non validé serait perdu).
 */
class DemandePretRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'membre_id' => ['required', 'integer'],
            'produit_credit_id' => ['required', 'integer'],
            'compte_epargne_id' => ['required', 'integer'],
            'montant' => ['required', new MontantSaisi],
            'nombre_echeances' => ['required', 'integer', 'min:1', 'max:520'],
            'methode' => ['required', Rule::enum(MethodeCalcul::class)],
            'objet' => ['required', 'string', 'max:500'],
            'garanties' => ['nullable', 'array', 'max:10'],
            'garanties.*.type' => ['required', Rule::in(['caution', 'bien'])],
            'garanties.*.membre_numero' => ['nullable', 'string', 'max:40'],
            'garanties.*.montant' => ['nullable', new MontantSaisi],
            'garanties.*.description' => ['nullable', 'string', 'max:500'],
            'garanties.*.valeur' => ['nullable', new MontantSaisi],
            // Pièce jointe : contrôle du type réel et de la taille, rangée hors du dossier public.
            'garanties.*.piece' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'garanties.*.piece.*' => 'Pièce jointe : PDF, JPEG ou PNG de 5 Mo au plus.',
            'required' => 'Ce champ est obligatoire.',
        ];
    }
}
