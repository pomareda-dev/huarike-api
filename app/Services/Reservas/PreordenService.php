<?php

namespace App\Services\Reservas;

use App\Exceptions\ReglaNegocioException;
use App\Models\Plato;

/**
 * Snapshot de la preorden: el precio y el nombre se congelan al reservar para
 * que la reserva histórica no cambie si la carta se actualiza.
 */
class PreordenService
{
    /**
     * @param  list<array{plato_id: int, cantidad: int}>  $solicitados
     * @return array{0: list<array<string, mixed>>, 1: float}
     */
    public function preparar(array $solicitados): array
    {
        if ($solicitados === []) {
            return [[], 0.0];
        }

        $ids = array_values(array_unique(array_map(static fn (array $fila) => (int) $fila['plato_id'], $solicitados)));

        $platos = Plato::query()->disponibles()->whereIn('id', $ids)->get()->keyBy('id');

        if ($platos->count() !== count($ids)) {
            throw new ReglaNegocioException(
                'Uno de los platos de tu preorden ya no está disponible.',
                ['motivo' => 'plato-no-disponible'],
            );
        }

        $filas = [];
        $subtotal = 0.0;

        foreach ($solicitados as $fila) {
            /** @var Plato $plato */
            $plato = $platos[(int) $fila['plato_id']];
            $cantidad = (int) $fila['cantidad'];

            $filas[] = [
                'plato_id' => $plato->id,
                'nombre' => $plato->nombre,
                'precio' => (float) $plato->precio,
                'cantidad' => $cantidad,
            ];

            $subtotal += (float) $plato->precio * $cantidad;
        }

        return [$filas, round($subtotal, 2)];
    }
}
