<?php

namespace App\Http\Requests\Panel;

use App\Enums\Rol;
use App\Rules\TelefonoPeruano;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de usuario desde el panel: el admin puede crear clientes o admins
 * (los admins no se autoregistran desde el sitio, docs §2).
 */
class GuardarUsuarioRequest extends FormRequest
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
            'telefono' => ['required', 'string', 'max:20', new TelefonoPeruano],
            'rol' => ['required', Rule::in(Rol::valores())],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'correo.unique' => 'Ese correo ya tiene una cuenta.',
            'correo.email' => 'Revisa el correo: falta un formato válido (ejemplo@dominio.com).',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'rol.in' => 'Elige un rol válido.',
        ];
    }
}
