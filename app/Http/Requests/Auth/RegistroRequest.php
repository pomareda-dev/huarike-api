<?php

namespace App\Http\Requests\Auth;

use App\Rules\TelefonoPeruano;
use Illuminate\Foundation\Http\FormRequest;

class RegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => is_string($this->nombre) ? trim($this->nombre) : $this->nombre,
            'correo' => is_string($this->correo) ? mb_strtolower(trim($this->correo)) : $this->correo,
            'telefono' => is_string($this->telefono) ? TelefonoPeruano::normalizar($this->telefono) : $this->telefono,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:120'],
            'correo' => ['required', 'string', 'email', 'max:190', 'unique:users,correo'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'telefono' => ['required', 'string', 'max:20', new TelefonoPeruano],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe tu nombre completo.',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'correo.required' => 'Escribe tu correo.',
            'correo.email' => 'Revisa el correo: falta un formato válido (ejemplo@dominio.com).',
            'correo.unique' => 'Ese correo ya tiene una cuenta. Inicia sesión.',
            'password.required' => 'Escribe una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'telefono.required' => 'Escribe tu teléfono.',
        ];
    }
}
