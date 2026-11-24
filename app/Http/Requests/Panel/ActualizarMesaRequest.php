<?php

namespace App\Http\Requests\Panel;

use App\Enums\Zona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarMesaRequest extends FormRequest
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
            'zona' => ['required', Rule::in(Zona::valores())],
            'capacidad' => [
                'required', 'integer',
                'between:'.config('huarike.mesas.capacidad_minima', 1).','.config('huarike.mesas.capacidad_maxima', 8),
            ],
            'activa' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zona.required' => 'Elige una zona del salón',
            'zona.in' => 'Elige una zona del salón',
            'capacidad.between' => 'La capacidad debe ser un número entre 1 y 8',
        ];
    }
}
