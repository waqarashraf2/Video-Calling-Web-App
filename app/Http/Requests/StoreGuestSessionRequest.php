<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuestSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'min:2', 'max:30', 'regex:/^[\pL\pN ._-]+$/u'],
            'adult' => ['accepted'],
            'terms' => ['accepted'],
        ];
    }
}
