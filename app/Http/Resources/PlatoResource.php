<?php

namespace App\Http\Resources;

use App\Models\Plato;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plato
 */
class PlatoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'categoria_id' => $this->categoria_id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'precio' => (float) $this->precio,
            'etiquetas' => $this->etiquetas ?? [],
            'foto' => $this->foto,
            'disponible' => $this->disponible,
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}
