<?php

namespace App\Http\Requests\Panel;

use App\Enums\EstadoReserva;
use App\Rules\FechaDeReserva;
use App\Rules\HoraDeTurno;
use App\Rules\TelefonoPeruano;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de reserva desde el panel: contacto completo y mesa obligatoria
 * (`validarReservaAdmin`).
 */
class GuardarReservaAdminRequest extends FormRequest
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
            'fecha' => ['required', 'date_format:Y-m-d', new FechaDeReserva],
            'hora' => ['required', 'date_format:H:i', new HoraDeTurno],
            'personas' => [
                'required', 'integer',
                'between:'.config('huarike.personas.minimo', 1).','.config('huarike.personas.maximo', 6),
            ],
            'mesa_id' => ['required', 'integer', 'exists:mesas,id'],
            'nombre' => ['required', 'string', 'min:3', 'max:120'],
            'correo' => ['required', 'string', 'email', 'max:190'],
            'telefono' => ['required', 'string', 'max:20', new TelefonoPeruano],
            'notas' => ['nullable', 'string', 'max:500'],
            'estado' => ['sometimes', Rule::in(EstadoReserva::valores())],
            'platos' => ['nullable', 'array', 'max:40'],
            'platos.*.plato_id' => ['required', 'integer', 'exists:platos,id'],
            'platos.*.cantidad' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mesa_id.required' => 'Elige una mesa para la reserva.',
            'mesa_id.exists' => 'Esa mesa no existe.',
            'nombre.required' => 'Escribe el nombre del cliente.',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'correo.email' => 'Revisa el correo: falta un formato válido (ejemplo@dominio.com).',
            'telefono.required' => 'Escribe el teléfono del cliente.',
            'personas.between' => 'La reserva es de 1 a 6 personas.',
        ];
    }
}
