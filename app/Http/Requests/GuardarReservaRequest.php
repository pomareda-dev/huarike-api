<?php

namespace App\Http\Requests;

use App\Rules\FechaDeReserva;
use App\Rules\HoraDeTurno;
use Illuminate\Foundation\Http\FormRequest;

class GuardarReservaRequest extends FormRequest
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
            'fecha' => ['required', 'date_format:Y-m-d', new FechaDeReserva],
            'hora' => ['required', 'date_format:H:i', new HoraDeTurno],
            'personas' => [
                'required', 'integer',
                'between:'.config('huarike.personas.minimo', 1).','.config('huarike.personas.maximo', 6),
            ],
            'mesa_id' => ['nullable', 'integer', 'exists:mesas,id'],
            'notas' => ['nullable', 'string', 'max:500'],
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
            'fecha.required' => 'Elige la fecha de tu reserva.',
            'fecha.date_format' => 'Elige una fecha válida.',
            'hora.required' => 'Elige el horario de tu reserva.',
            'hora.date_format' => 'Elige un horario de reserva disponible.',
            'personas.required' => 'Indica para cuántas personas es la reserva.',
            'personas.between' => 'La reserva es de 1 a 6 personas.',
            'mesa_id.exists' => 'Esa mesa no existe. Elige otra.',
            'platos.*.plato_id.required' => 'Elige un plato de la carta.',
            'platos.*.plato_id.exists' => 'Uno de los platos ya no existe.',
            'platos.*.cantidad.min' => 'La cantidad mínima por plato es 1.',
            'platos.*.cantidad.max' => 'La cantidad máxima por plato es 20.',
        ];
    }
}
