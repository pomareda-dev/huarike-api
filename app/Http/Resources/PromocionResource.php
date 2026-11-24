<?php

namespace App\Http\Resources;

use App\Models\Promocion;
use App\Services\Archivos\FotoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Promocion
 */
class PromocionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'destacada' => $this->destacada,
            'vigencia' => $this->vigencia,
            'categoria' => $this->categoria?->value,
            'condiciones' => $this->condiciones ?? [],
            'codigo' => $this->codigo,
            'precio_texto' => $this->precio_texto,
            'foto' => $this->foto === null ? null : FotoService::url($this->foto),
            'activa' => $this->activa,
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}
