<?php

namespace App\Http\Requests\Panel;

use App\Rules\HoraValida;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => is_string($this->nombre) ? trim($this->nombre) : $this->nombre,
            'slots' => $this->normalizarSlots($this->slots),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:60'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*' => ['required', new HoraValida('Cada horario debe tener el formato HH:MM.')],
            'activa' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre del turno.',
            'slots.min' => 'Agrega al menos un horario al turno.',
            'slots.*.date_format' => 'Cada horario debe tener el formato HH:MM.',
        ];
    }

    /**
     * @return list<string>|mixed
     */
    private function normalizarSlots(mixed $slots): mixed
    {
        if (! is_array($slots)) {
            return $slots;
        }

        $limpios = array_values(array_filter(array_map(
            static fn ($slot) => is_string($slot) ? substr(trim($slot), 0, 5) : $slot,
            $slots,
        ), static fn ($slot) => $slot !== null && $slot !== ''));

        $limpios = array_values(array_unique($limpios));
        sort($limpios);

        return $limpios;
    }
}
