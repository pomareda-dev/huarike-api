<?php

namespace App\Http\Requests\Panel;

use App\Enums\Rol;
use App\Rules\TelefonoPeruano;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarUsuarioRequest extends FormRequest
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
        $usuario = $this->route('usuario');

        return [
            'nombre' => ['required', 'string', 'min:3', 'max:120'],
            'correo' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'correo')->ignore($usuario?->id)],
            'telefono' => ['required', 'string', 'max:20', new TelefonoPeruano],
            'rol' => ['required', Rule::in(Rol::valores())],
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
            'rol.in' => 'Elige un rol válido.',
        ];
    }
}
