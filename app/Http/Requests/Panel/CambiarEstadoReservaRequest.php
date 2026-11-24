<?php

namespace App\Http\Requests\Panel;

use App\Enums\EstadoReserva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoReservaRequest extends FormRequest
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
            'estado' => ['required', Rule::in(EstadoReserva::valores())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'Elige el nuevo estado.',
            'estado.in' => 'Ese estado no es válido.',
        ];
    }
}
