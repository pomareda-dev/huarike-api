<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload de foto de platos y promociones (docs §8.3).
 */
class SubirFotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.required' => 'Elige una imagen para subir.',
            'foto.mimes' => 'La foto debe ser JPG, PNG o WEBP.',
            'foto.max' => 'La foto no debe superar los 4 MB.',
        ];
    }
}
