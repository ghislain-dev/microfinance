<?php

declare(strict_types=1);

namespace App\Http\Requests\Epargne;

use App\Support\Montant\Devise;
use App\Support\Montant\Montant;
use App\Support\Montant\MontantSaisi;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dépôt, retrait ou virement saisi au guichet. La clé (UUID) est générée à l'affichage du formulaire :
 * un double envoi ne crée qu'une opération.
 */
class OperationEpargneRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'montant' => ['required', new MontantSaisi],
            'cle' => ['required', 'uuid'],
            'destination' => $this->routeIs('comptes-epargne.virements') ? ['required', 'string', 'max:60'] : [],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['cle.*' => 'Formulaire expiré : rechargez la page.', 'destination.required' => 'Numéro du compte destinataire obligatoire.'];
    }

    public function montant(Devise $devise): Montant
    {
        return Montant::depuisSaisie($this->string('montant')->value(), $devise);
    }
}
