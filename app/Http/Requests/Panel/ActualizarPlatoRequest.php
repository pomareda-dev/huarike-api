<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarPlatoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => is_string($this->nombre) ? trim($this->nombre) : $this->nombre,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $plato = $this->route('plato');

        return [
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'nombre' => [
                'required', 'string', 'min:3', 'max:150',
                // El nombre es único dentro de su categoría.
                Rule::unique('platos', 'nombre')
                    ->where(fn ($query) => $query->where('categoria_id', $this->input('categoria_id')))
                    ->ignore($plato?->id),
            ],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'precio' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'etiquetas' => ['nullable', 'array', 'max:10'],
            'etiquetas.*' => ['string', 'max:40'],
            'disponible' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'categoria_id.required' => 'Elige una categoría.',
            'categoria_id.exists' => 'Elige una categoría válida.',
            'nombre.required' => 'Escribe el nombre del plato.',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.unique' => 'Ya existe un plato con ese nombre en la categoría.',
            'precio.required' => 'Escribe el precio del plato.',
            'precio.gt' => 'El precio debe ser mayor a 0.',
        ];
    }
}
