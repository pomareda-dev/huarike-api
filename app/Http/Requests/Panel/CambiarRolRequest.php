<?php

namespace App\Http\Requests\Panel;

use App\Enums\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarRolRequest extends FormRequest
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
            'rol' => ['required', Rule::in(Rol::valores())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rol.required' => 'Elige un rol.',
            'rol.in' => 'Elige un rol válido.',
        ];
    }
}
