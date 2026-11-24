<?php

namespace App\Services\Panel;

use App\Exceptions\ReglaNegocioException;
use App\Models\Horario;
use App\Models\Reserva;
use App\Models\Turno;
use App\Support\Fechas;
use Illuminate\Support\Collection;

/**
 * Franjas de atención y turnos editables desde el panel (docs §5.9). Todo lo
 * que depende de turnos lee estas tablas, así que el borrado se protege con
 * las reservas futuras.
 */
class HorarioService
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearHorario(array $datos): Horario
    {
        return Horario::create([
            'nombre' => $datos['nombre'],
            'dias' => array_values(array_map('intval', $datos['dias'])),
            'abre' => substr($datos['abre'], 0, 5),
            'cierra' => substr($datos['cierra'], 0, 5),
            'activa' => (bool) ($datos['activa'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizarHorario(Horario $horario, array $datos): Horario
    {
        $horario->fill([
            'nombre' => $datos['nombre'],
            'dias' => array_values(array_map('intval', $datos['dias'])),
            'abre' => substr($datos['abre'], 0, 5),
            'cierra' => substr($datos['cierra'], 0, 5),
            'activa' => array_key_exists('activa', $datos) ? (bool) $datos['activa'] : $horario->activa,
        ])->save();

        return $horario;
    }

    public function eliminarHorario(Horario $horario): void
    {
        $futuras = $this->reservasFuturas()
            ->filter(function (Reserva $reserva) use ($horario): bool {
                $dia = Fechas::diaSemana($this->fechaDe($reserva));

                if (! in_array($dia, array_map('intval', $horario->dias ?? []), true)) {
                    return false;
                }

                $hora = Fechas::minutos($reserva->hora);

                return $hora >= Fechas::minutos($horario->abre) && $hora < Fechas::minutos($horario->cierra);
            })
            ->count();

        if ($futuras > 0) {
            throw new ReglaNegocioException(
                'No puedes eliminar esta franja: tiene reservas futuras.',
                ['motivo' => 'franja-con-reservas'],
            );
        }

        $horario->delete();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearTurno(array $datos): Turno
    {
        return Turno::create([
            'id' => $datos['id'],
            'nombre' => $datos['nombre'],
            'slots' => array_values($datos['slots']),
            'activa' => (bool) ($datos['activa'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizarTurno(Turno $turno, array $datos): Turno
    {
        $turno->fill([
            'nombre' => $datos['nombre'],
            'slots' => array_values($datos['slots']),
            'activa' => array_key_exists('activa', $datos) ? (bool) $datos['activa'] : $turno->activa,
        ])->save();

        return $turno;
    }

    public function eliminarTurno(Turno $turno): void
    {
        $slots = array_map(static fn (string $slot) => substr($slot, 0, 5), $turno->slots ?? []);

        $futuras = $this->reservasFuturas()
            ->filter(fn (Reserva $reserva) => in_array(substr((string) $reserva->hora, 0, 5), $slots, true))
            ->count();

        if ($futuras > 0) {
            throw new ReglaNegocioException(
                'No puedes eliminar este turno: tiene reservas futuras.',
                ['motivo' => 'turno-con-reservas'],
            );
        }

        $turno->delete();
    }

    /**
     * @return Collection<int, Reserva>
     */
    private function reservasFuturas(): Collection
    {
        return Reserva::query()
            ->whereDate('fecha', '>=', Fechas::hoy()->toDateString())
            ->whereNotIn('estado', ['cancelada', 'lista-espera'])
            ->get();
    }

    private function fechaDe(Reserva $reserva): string
    {
        return $reserva->fecha instanceof \DateTimeInterface
            ? $reserva->fecha->format('Y-m-d')
            : substr((string) $reserva->fecha, 0, 10);
    }
}
