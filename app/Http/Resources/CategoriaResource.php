<?php

namespace App\Http\Resources;

use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Categoria
 */
class CategoriaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'posicion' => $this->posicion,
            'activa' => $this->activa,
            'platos' => PlatoResource::collection($this->whenLoaded('platos')),
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}
