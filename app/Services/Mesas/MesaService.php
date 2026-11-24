<?php

namespace App\Services\Mesas;

use App\Exceptions\ReglaNegocioException;
use App\Models\Mesa;
use App\Models\Reserva;
use Illuminate\Support\Facades\DB;

/**
 * Mesas del salón (docs §5.2): numeración consecutiva, activación y borrado
 * protegido por las reservas futuras.
 */
class MesaService
{
    /**
     * @param  array{zona: string, capacidad: int}  $datos
     */
    public function crear(array $datos): Mesa
    {
        return DB::transaction(function () use ($datos): Mesa {
            // "mayor + 1" sobre las mesas existentes, incluidas las borradas,
            // para no reutilizar un número que aparece en reservas históricas.
            $siguiente = ((int) Mesa::withTrashed()->max('numero')) + 1;

            return Mesa::create([
                'numero' => $siguiente,
                'zona' => $datos['zona'],
                'capacidad' => $datos['capacidad'],
                'activa' => true,
            ]);
        });
    }

    /**
     * @param  array{zona: string, capacidad: int, activa?: bool}  $datos
     */
    public function actualizar(Mesa $mesa, array $datos): Mesa
    {
        $mesa->fill([
            'zona' => $datos['zona'],
            'capacidad' => $datos['capacidad'],
            'activa' => $datos['activa'] ?? $mesa->activa,
        ])->save();

        return $mesa;
    }

    public function desactivar(Mesa $mesa): Mesa
    {
        $mesa->forceFill(['activa' => false])->save();

        return $mesa;
    }

    public function activar(Mesa $mesa): Mesa
    {
        $mesa->forceFill(['activa' => true])->save();

        return $mesa;
    }

    /**
     * Una mesa con reservas futuras activas no se puede borrar.
     */
    public function eliminar(Mesa $mesa): void
    {
        $futuras = Reserva::query()
            ->where('mesa_id', $mesa->id)
            ->whereDate('fecha', '>=', now(config('huarike.zona_horaria'))->toDateString())
            ->whereNotIn('estado', ['cancelada', 'lista-espera'])
            ->count();

        if ($futuras > 0) {
            throw new ReglaNegocioException(
                'Esta mesa tiene reservas futuras activas. Cámbialas de mesa antes de eliminarla.',
                ['motivo' => 'mesa-con-reservas'],
            );
        }

        $mesa->delete();
    }

    /**
     * Asientos de las mesas activas (`asientosActivos`).
     */
    public function asientosActivos(): int
    {
        return (int) Mesa::query()->activas()->sum('capacidad');
    }
}
