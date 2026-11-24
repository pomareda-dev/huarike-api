<?php

namespace App\Services\Panel;

use App\Enums\EstadoReserva;
use App\Models\Reserva;
use App\Models\Turno;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Listado, filtros y CSV de reservas del panel (docs §5.4).
 */
class ReservaPanelService
{
    /**
     * Consulta base con los filtros del listado: fecha, turno, estado y texto.
     *
     * @return Builder<Reserva>
     */
    public function consulta(Request $request): Builder
    {
        $fecha = $request->query('fecha');
        $turno = $request->query('turno');
        $estado = $request->query('estado', 'todos');
        $texto = is_string($request->query('texto')) ? $request->query('texto') : '';

        return Reserva::query()
            ->with(['mesa'])
            ->when(is_string($fecha) && $fecha !== '', fn (Builder $query) => $query->whereDate('fecha', $fecha))
            ->when($this->horasDelTurno($turno) !== [], function (Builder $query) use ($turno): void {
                $horas = $this->horasDelTurno($turno);
                // La columna es TIME: se compara por HH:MM para no depender de
                // los segundos que agrega MySQL.
                $marcadores = implode(',', array_fill(0, count($horas), '?'));
                $query->whereRaw("TIME_FORMAT(hora, '%H:%i') IN ({$marcadores})", $horas);
            })
            ->when($estado === 'pendiente', fn (Builder $query) => $query->whereIn(
                'estado',
                array_map(fn (EstadoReserva $e) => $e->value, EstadoReserva::pendientes()),
            ))
            ->when(
                $estado !== 'todos' && $estado !== 'pendiente' && in_array($estado, EstadoReserva::valores(), true),
                fn (Builder $query) => $query->where('estado', $estado),
            )
            ->when(Texto::normalizar($texto) !== '', fn (Builder $query) => $this->aplicarTexto($query, Texto::normalizar($texto)));
    }

    /**
     * Agrega el patrón de búsqueda en nombre, correo, teléfono, folio y mesa
     * (incluida la zona), insensible a tildes y mayúsculas.
     *
     * @param  Builder<Reserva>  $query
     */
    private function aplicarTexto(Builder $query, string $buscado): void
    {
        $patron = '%'.$buscado.'%';

        $query->where(function (Builder $sub) use ($patron): void {
            $sub->whereRaw('LOWER(nombre) LIKE ?', [$patron])
                ->orWhereRaw('LOWER(correo) LIKE ?', [$patron])
                ->orWhereRaw('LOWER(telefono) LIKE ?', [$patron])
                ->orWhereRaw('LOWER(folio) LIKE ?', [$patron])
                ->orWhereRaw("LOWER(CONCAT('mesa ', COALESCE((SELECT numero FROM mesas WHERE mesas.id = reservas.mesa_id), ''))) LIKE ?", [$patron])
                ->orWhereHas('mesa', fn (Builder $mesa) => $mesa
                    ->whereRaw('LOWER(zona) LIKE ?', [$patron])
                    ->orWhereRaw("LOWER(CONCAT('mesa ', numero)) LIKE ?", [$patron]))
                ->orWhereRaw("LOWER('sin mesa') LIKE ?", [$patron]);
        });
    }

    /**
     * Horas de los slots del turno indicado; vacío si no se filtró un turno
     * válido.
     *
     * @return list<string>
     */
    public function horasDelTurno(mixed $turnoId): array
    {
        if (! is_string($turnoId) || $turnoId === '' || $turnoId === 'todos') {
            return [];
        }

        $turno = Turno::query()->find($turnoId);

        if ($turno === null) {
            return [];
        }

        return array_map(static fn (string $slot) => substr($slot, 0, 5), $turno->slots ?? []);
    }

    /**
     * Pie de tabla: cuántas reservas y cuántas personas suma el conjunto
     * filtrado (`resumenReservas`).
     *
     * @param  Builder<Reserva>  $consulta
     * @return array{reservas: int, personas: int}
     */
    public function resumen(Builder $consulta): array
    {
        $filas = $consulta->reorder()->get(['personas']);

        return [
            'reservas' => $filas->count(),
            'personas' => (int) $filas->sum('personas'),
        ];
    }
}
