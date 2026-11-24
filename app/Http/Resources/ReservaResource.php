<?php

namespace App\Http\Resources;

use App\Enums\EstadoReserva;
use App\Models\Reserva;
use App\Models\Turno;
use App\Support\Fechas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reserva completa: contacto, mesa, preorden, historial y los campos que
 * pinta la pantalla de confirmación (docs §5.3 RC7).
 *
 * @mixin Reserva
 */
class ReservaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fecha = $this->fecha instanceof \DateTimeInterface
            ? $this->fecha->format('Y-m-d')
            : substr((string) $this->fecha, 0, 10);

        return [
            'folio' => $this->folio,
            'fecha' => $fecha,
            'fecha_larga' => Fechas::larga($fecha),
            'hora' => $this->hora,
            'turno' => $this->turno(),
            'personas' => $this->personas,
            'estado' => $this->estado instanceof EstadoReserva ? $this->estado->value : $this->estado,
            'estado_etiqueta' => $this->estado instanceof EstadoReserva ? $this->estado->etiqueta() : null,
            'nombre' => $this->nombre,
            'correo' => $this->correo,
            'telefono' => $this->telefono,
            'notas' => $this->notas,
            'subtotal' => (float) $this->subtotal,
            'creada_por' => $this->creada_por,
            'tolerancia_minutos' => (int) config('huarike.tolerancia_minutos', 15),
            'mesa' => $this->mesa_id === null ? null : [
                'id' => $this->mesa?->id,
                'numero' => $this->mesa?->numero,
                'zona' => $this->mesa?->zona?->value,
                'capacidad' => $this->mesa?->capacidad,
            ],
            'platos' => $this->whenLoaded('platos', fn () => $this->platos->map(fn ($plato) => [
                'plato_id' => $plato->plato_id,
                'nombre' => $plato->nombre,
                'precio' => (float) $plato->precio,
                'cantidad' => $plato->cantidad,
                'importe' => (float) $plato->precio * $plato->cantidad,
            ])->all()),
            'historial' => $this->whenLoaded('historial', fn () => $this->historial->map(fn ($entrada) => [
                'tipo' => $entrada->tipo,
                'texto' => $entrada->texto,
                'autor' => $entrada->autor,
                'cuando' => $entrada->created_at?->toIso8601String(),
            ])->all()),
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Turno al que pertenece la hora, leído de la tabla `turnos`.
     */
    private function turno(): ?string
    {
        $hora = substr((string) $this->hora, 0, 5);

        return Turno::query()
            ->activos()
            ->get()
            ->first(fn ($turno) => in_array($hora, array_map(
                static fn (string $slot) => substr($slot, 0, 5),
                $turno->slots ?? [],
            ), true))
            ?->id;
    }
}
