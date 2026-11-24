<?php

namespace App\Http\Resources;

use App\Models\Horario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Horario
 */
class HorarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'dias' => array_map('intval', $this->dias ?? []),
            'abre' => substr((string) $this->abre, 0, 5),
            'cierra' => substr((string) $this->cierra, 0, 5),
            'activa' => $this->activa,
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}
