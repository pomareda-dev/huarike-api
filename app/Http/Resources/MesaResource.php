<?php

namespace App\Http\Resources;

use App\Models\Mesa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Mesa
 */
class MesaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'zona' => $this->zona->value,
            'capacidad' => $this->capacidad,
            'activa' => $this->activa,
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}
