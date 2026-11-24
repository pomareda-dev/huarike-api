<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RecuperarClaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'correo' => is_string($this->correo) ? mb_strtolower(trim($this->correo)) : $this->correo,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'correo' => ['required', 'string', 'email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'correo.required' => 'Escribe el correo de tu cuenta.',
            'correo.email' => 'Revisa el correo: falta un formato válido (ejemplo@dominio.com).',
        ];
    }
}
