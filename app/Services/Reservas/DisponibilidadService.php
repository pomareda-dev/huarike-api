<?php

namespace App\Services\Reservas;

use App\Enums\EstadoReserva;
use App\Enums\EstadoSlot;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Turno;
use App\Support\Fechas;
use Illuminate\Support\Collection;

/**
 * Disponibilidad calculada solo con reservas reales (la simulación del
 * prototipo no se replica, docs §5.2) y con los turnos vigentes de la tabla.
 */
class DisponibilidadService
{
    /**
     * Estado de cada slot del día para un grupo de N personas.
     *
     * @return array{fecha: string, personas: int, mesas_activas: int, slots: list<array<string, mixed>>}
     */
    public function delDia(string $fecha, int $personas): array
    {
        $reservas = $this->reservasDelDia($fecha);
        $mesas = Mesa::query()->activas()->orderBy('numero')->get();
        $slots = [];

        foreach ($this->turnosVigentes() as $turno) {
            foreach ($turno->slots as $hora) {
                $libres = $this->mesasLibres($mesas, $reservas, $hora, $personas);
                $estado = EstadoSlot::desdeMesasLibres($libres->count());

                $slots[] = [
                    'hora' => substr($hora, 0, 5),
                    'turno' => $turno->id,
                    'estado' => $estado->value,
                    'mesas_libres' => $libres->count(),
                    'resumen' => $estado->resumen($libres->count()),
                    'pico' => in_array(substr($hora, 0, 5), config('huarike.horas_pico', []), true),
                ];
            }
        }

        return [
            'fecha' => $fecha,
            'personas' => $personas,
            'mesas_activas' => $mesas->count(),
            'slots' => $slots,
        ];
    }

    /**
     * Mesas concretas que pueden recibir al grupo en una franja.
     *
     * @return Collection<int, Mesa>
     */
    public function mesasPara(string $fecha, string $hora, int $personas, ?int $ignorarReservaId = null): Collection
    {
        $reservas = $this->reservasDelDia($fecha, $ignorarReservaId);
        $mesas = Mesa::query()->activas()->orderBy('numero')->get();

        return $this->mesasLibres($mesas, $reservas, $hora, $personas);
    }

    /**
     * Regla de choque: misma mesa, misma fecha y menos de 90 minutos de
     * diferencia; `lista-espera` y `cancelada` no ocupan franja.
     */
    public function hayConflicto(
        Collection $reservas,
        ?int $mesaId,
        string $hora,
        ?int $ignorarReservaId = null,
    ): bool {
        if ($mesaId === null) {
            return false;
        }

        $inicio = Fechas::minutos($hora);
        $duracion = (int) config('huarike.duracion_minutos', 90);

        return $reservas->contains(function (Reserva $reserva) use ($mesaId, $inicio, $duracion, $ignorarReservaId): bool {
            if ($reserva->mesa_id !== $mesaId) {
                return false;
            }

            if ($ignorarReservaId !== null && $reserva->id === $ignorarReservaId) {
                return false;
            }

            return abs(Fechas::minutos($reserva->hora) - $inicio) < $duracion;
        });
    }

    /**
     * @return Collection<int, Reserva>
     */
    public function reservasDelDia(string $fecha, ?int $ignorarReservaId = null): Collection
    {
        return Reserva::query()
            ->whereDate('fecha', $fecha)
            ->whereNotIn('estado', array_map(
                fn (EstadoReserva $estado) => $estado->value,
                EstadoReserva::sinChoque(),
            ))
            ->when($ignorarReservaId !== null, fn ($query) => $query->whereKeyNot($ignorarReservaId))
            ->get();
    }

    /**
     * @return Collection<int, Turno>
     */
    public function turnosVigentes(): Collection
    {
        return Turno::query()->activos()->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, Mesa>  $mesas
     * @param  Collection<int, Reserva>  $reservas
     * @return Collection<int, Mesa>
     */
    private function mesasLibres(Collection $mesas, Collection $reservas, string $hora, int $personas): Collection
    {
        return $mesas
            ->filter(fn (Mesa $mesa) => $mesa->capacidad >= $personas)
            ->filter(fn (Mesa $mesa) => ! $this->hayConflicto($reservas, $mesa->id, $hora))
            ->values();
    }
}
