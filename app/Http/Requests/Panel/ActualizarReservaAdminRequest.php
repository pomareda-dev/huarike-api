<?php

namespace App\Http\Requests\Panel;

use App\Rules\FechaDeReserva;
use App\Rules\HoraDeTurno;
use App\Rules\TelefonoPeruano;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarReservaAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Solo se normalizan los campos enviados: la edición es parcial.
        $normalizado = [];

        if (is_string($this->nombre)) {
            $normalizado['nombre'] = trim($this->nombre);
        }

        if (is_string($this->correo)) {
            $normalizado['correo'] = mb_strtolower(trim($this->correo));
        }

        if (is_string($this->telefono)) {
            $normalizado['telefono'] = TelefonoPeruano::normalizar($this->telefono);
        }

        if ($normalizado !== []) {
            $this->merge($normalizado);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['sometimes', 'date_format:Y-m-d', new FechaDeReserva],
            'hora' => ['sometimes', 'date_format:H:i', new HoraDeTurno],
            'personas' => [
                'sometimes', 'integer',
                'between:'.config('huarike.personas.minimo', 1).','.config('huarike.personas.maximo', 6),
            ],
            'mesa_id' => ['sometimes', 'integer', 'exists:mesas,id'],
            'nombre' => ['sometimes', 'string', 'min:3', 'max:120'],
            'correo' => ['sometimes', 'string', 'email', 'max:190'],
            'telefono' => ['sometimes', 'string', 'max:20', new TelefonoPeruano],
            'notas' => ['nullable', 'string', 'max:500'],
            'platos' => ['sometimes', 'nullable', 'array', 'max:40'],
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
            'personas.between' => 'La reserva es de 1 a 6 personas.',
            'correo.email' => 'Revisa el correo: falta un formato válido (ejemplo@dominio.com).',
            'mesa_id.exists' => 'Esa mesa no existe.',
        ];
    }
}
