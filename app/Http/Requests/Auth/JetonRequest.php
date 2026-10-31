<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class JetonRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'appareil' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'regex:/^\d{'.(int) config('otp.longueur').'}$/'],
        ];
    }
}
