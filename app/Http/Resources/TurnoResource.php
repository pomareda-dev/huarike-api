<?php

namespace App\Http\Resources;

use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Turno
 */
class TurnoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slots' => array_values($this->slots ?? []),
            'activa' => $this->activa,
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}
