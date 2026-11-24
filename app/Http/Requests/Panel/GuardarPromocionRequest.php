<?php

namespace App\Http\Requests\Panel;

use App\Enums\CategoriaPromocion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarPromocionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => is_string($this->nombre) ? trim($this->nombre) : $this->nombre,
            'codigo' => is_string($this->codigo) ? strtoupper(trim($this->codigo)) : $this->codigo,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:160', 'unique:promociones,slug'],
            'destacada' => ['sometimes', 'boolean'],
            'vigencia' => ['required', 'string', 'max:120'],
            'categoria' => ['required', Rule::in(CategoriaPromocion::valores())],
            'condiciones' => ['nullable', 'array', 'max:10'],
            'condiciones.*' => ['string', 'max:120'],
            'codigo' => ['required', 'string', 'max:30'],
            'precio_texto' => ['required', 'string', 'max:30'],
            'activa' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre de la promoción.',
            'vigencia.required' => 'Indica la vigencia visible.',
            'categoria.required' => 'Elige la categoría de la promoción.',
            'categoria.in' => 'Elige una categoría válida.',
            'codigo.required' => 'Escribe el código de la promoción.',
            'precio_texto.required' => 'Escribe el precio visible.',
            'slug.unique' => 'Ya existe una promoción con ese slug.',
        ];
    }
}
