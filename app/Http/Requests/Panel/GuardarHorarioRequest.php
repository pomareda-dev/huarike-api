<?php

namespace App\Http\Requests\Panel;

use App\Rules\HoraValida;
use Illuminate\Foundation\Http\FormRequest;

class GuardarHorarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => is_string($this->nombre) ? trim($this->nombre) : $this->nombre,
            'dias' => is_array($this->dias)
                ? array_values(array_unique(array_map('intval', $this->dias)))
                : $this->dias,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:60'],
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['integer', 'between:0,6'],
            'abre' => ['required', new HoraValida('Usa el formato HH:MM para la apertura.')],
            'cierra' => ['required', new HoraValida('Usa el formato HH:MM para el cierre.'), 'after:abre'],
            'activa' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre de la franja.',
            'dias.required' => 'Elige al menos un día de atención.',
            'dias.min' => 'Elige al menos un día de atención.',
            'dias.*.between' => 'Los días deben estar entre 0 (domingo) y 6 (sábado).',
            'abre.required' => 'Indica la hora de apertura.',
            'cierra.required' => 'Indica la hora de cierre.',
            'cierra.after' => 'La hora de cierre debe ser posterior a la de apertura.',
        ];
    }
}
