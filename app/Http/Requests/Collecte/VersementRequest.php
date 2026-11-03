<?php

declare(strict_types=1);

namespace App\Http\Requests\Collecte;

use App\Http\Requests\Caisse\BilletageRequest;

class VersementRequest extends BilletageRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'collecteur_id' => ['required', 'integer']];
    }
}
