<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'correo.required' => 'Escribe tu correo.',
            'correo.email' => 'Revisa el correo: falta un formato válido (ejemplo@dominio.com).',
            'password.required' => 'Escribe tu contraseña.',
        ];
    }
}
