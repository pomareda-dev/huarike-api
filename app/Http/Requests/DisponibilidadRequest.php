<?php

namespace App\Http\Requests;

use App\Rules\FechaDeReserva;
use Illuminate\Foundation\Http\FormRequest;

class DisponibilidadRequest extends FormRequest
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
            'personas' => [
                'required', 'integer',
                'between:'.config('huarike.personas.minimo', 1).','.config('huarike.personas.maximo', 6),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'Elige la fecha de tu reserva.',
            'personas.required' => 'Indica para cuántas personas es la reserva.',
            'personas.between' => 'La reserva es de 1 a 6 personas.',
        ];
    }
}
