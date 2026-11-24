<?php

namespace App\Services\Panel;

use App\Enums\EstadoReserva;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Turno;
use App\Support\Fechas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * KPIs del panel (docs §5.5) y celdas del calendario (docs §5.6). Todo se
 * calcula por SQL y en memoria a partir de reservas reales.
 */
class ReporteService
{
    /**
     * Métricas del día (`metricasDelDia`).
     *
     * @return array<string, mixed>
     */
    public function delDia(string $fecha): array
    {
        $reservasHoy = $this->reservasEntre($fecha, $fecha);
        $reservasAyer = $this->reservasEntre(
            Fechas::carbon($fecha)->subDay()->toDateString(),
            Fechas::carbon($fecha)->subDay()->toDateString(),
        );

        $reservas = $this->contarReservas($reservasHoy);
        $noShow = $this->contarNoShow($reservasHoy);
        $mesasActivas = Mesa::query()->activas()->count();

        return [
            'fecha' => $fecha,
            'reservas' => $reservas,
            'comensales' => $this->contarComensales($reservasHoy),
            'no_show' => $noShow,
            'porcentaje_no_show' => $reservas === 0 ? 0 : (int) round($noShow / $reservas * 100),
            'reservas_ayer' => $this->contarReservas($reservasAyer),
            'delta' => $reservas - $this->contarReservas($reservasAyer),
            'mesas_activas' => $mesasActivas,
            'ocupacion' => $this->ocupacion($reservasHoy, $mesasActivas),
        ];
    }

    /**
     * Serie diaria para el gráfico de barras (`/panel/reportes/rango`).
     *
     * @return array{desde: string, hasta: string, dias: list<array<string, mixed>>}
     */
    public function rango(string $desde, string $hasta): array
    {
        $reservas = $this->reservasEntre($desde, $hasta)->groupBy(
            fn (Reserva $reserva) => $this->fechaDe($reserva),
        );

        $mesasActivas = Mesa::query()->activas()->count();
        $dias = [];
        $cursor = Fechas::carbon($desde);
        $limite = Fechas::carbon($hasta);

        while ($cursor->lessThanOrEqualTo($limite)) {
            $fecha = $cursor->toDateString();
            $delDia = $reservas->get($fecha, collect());

            $dias[] = [
                'fecha' => $fecha,
                'reservas' => $this->contarReservas($delDia),
                'comensales' => $this->contarComensales($delDia),
                'ocupacion' => $this->ocupacion($delDia, $mesasActivas)['promedio'],
            ];

            $cursor = $cursor->addDay();
        }

        return ['desde' => $desde, 'hasta' => $hasta, 'dias' => $dias];
    }

    /**
     * Celdas del calendario mensual: siempre 42, de lunes a domingo.
     *
     * @return array{anio: int, mes: int, celdas: list<array<string, mixed>>}
     */
    public function calendarioMes(int $anio, int $mes): array
    {
        $primero = CarbonImmutable::create($anio, $mes, 1, 0, 0, 0, Fechas::zona());
        $inicio = $primero->startOfWeek(CarbonImmutable::MONDAY);
        $fin = $inicio->addDays(41)->toDateString();

        return [
            'anio' => $anio,
            'mes' => $mes,
            'celdas' => $this->celdas($inicio->toDateString(), $fin, $mes),
        ];
    }

    /**
     * Celdas de la semana que contiene la fecha indicada (7 días).
     *
     * @return array{fecha: string, desde: string, hasta: string, celdas: list<array<string, mixed>>}
     */
    public function calendarioSemana(string $fecha): array
    {
        $inicio = Fechas::carbon($fecha)->startOfWeek(CarbonImmutable::MONDAY);
        $fin = $inicio->addDays(6);

        return [
            'fecha' => $fecha,
            'desde' => $inicio->toDateString(),
            'hasta' => $fin->toDateString(),
            'celdas' => $this->celdas($inicio->toDateString(), $fin->toDateString(), null),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function celdas(string $desde, string $hasta, ?int $mes): array
    {
        $reservas = $this->reservasEntre($desde, $hasta)->groupBy(
            fn (Reserva $reserva) => $this->fechaDe($reserva),
        );

        $mesasActivas = Mesa::query()->activas()->count();
        $hoy = Fechas::hoy()->toDateString();
        $celdas = [];
        $cursor = Fechas::carbon($desde);
        $limite = Fechas::carbon($hasta);

        while ($cursor->lessThanOrEqualTo($limite)) {
            $delDia = $reservas->get($cursor->toDateString(), collect());

            $celdas[] = [
                'fecha' => $cursor->toDateString(),
                'dia' => (int) $cursor->day,
                'del_mes' => $mes === null || (int) $cursor->month === $mes,
                'pasado' => $cursor->toDateString() < $hoy,
                'es_hoy' => $cursor->toDateString() === $hoy,
                'reservas' => $this->contarReservas($delDia),
                'personas' => $this->contarComensales($delDia),
                'ocupacion' => $this->ocupacion($delDia, $mesasActivas),
                'ocupadas_pico' => $this->ocupadasEnPico($delDia),
                'mesas_activas' => $mesasActivas,
            ];

            $cursor = $cursor->addDay();
        }

        return $celdas;
    }

    /**
     * Ocupación por turno: mesas ocupadas en la franja / (mesas activas ×
     * slots del turno), en porcentaje entero.
     *
     * @param  Collection<int, Reserva>  $reservas
     * @return array{almuerzo: int, cena: int, promedio: int, por_turno: array<string, int>}
     */
    public function ocupacion(Collection $reservas, int $mesasActivas): array
    {
        $porTurno = [];
        $turnos = Turno::query()->activos()->get();

        foreach ($turnos as $turno) {
            $slots = array_map(static fn (string $slot) => substr($slot, 0, 5), $turno->slots ?? []);
            $ocupadas = 0;

            foreach ($slots as $slot) {
                $ocupadas += $reservas
                    ->filter(fn (Reserva $reserva) => substr((string) $reserva->hora, 0, 5) === $slot)
                    ->pluck('mesa_id')
                    ->filter()
                    ->unique()
                    ->count();
            }

            $capacidad = $mesasActivas * max(1, count($slots));
            $porTurno[$turno->id] = $capacidad === 0 ? 0 : (int) round($ocupadas / $capacidad * 100);
        }

        $valores = array_values($porTurno);

        return [
            'almuerzo' => $porTurno['almuerzo'] ?? 0,
            'cena' => $porTurno['cena'] ?? 0,
            'promedio' => $valores === [] ? 0 : (int) round(array_sum($valores) / count($valores)),
            'por_turno' => $porTurno,
        ];
    }

    /**
     * Mesas ocupadas distintas en las horas pico del día.
     *
     * @param  Collection<int, Reserva>  $reservas
     */
    private function ocupadasEnPico(Collection $reservas): int
    {
        $pico = config('huarike.horas_pico', []);

        return $reservas
            ->filter(fn (Reserva $reserva) => in_array(substr((string) $reserva->hora, 0, 5), $pico, true))
            ->pluck('mesa_id')
            ->filter()
            ->unique()
            ->count();
    }

    /**
     * Reservas del rango, sin las de lista de espera para los contadores y sin
     * canceladas para la ocupación.
     *
     * @return Collection<int, Reserva>
     */
    private function reservasEntre(string $desde, string $hasta): Collection
    {
        return Reserva::query()
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->get();
    }

    /**
     * @param  Collection<int, Reserva>  $reservas
     */
    private function contarReservas(Collection $reservas): int
    {
        return $reservas
            ->reject(fn (Reserva $reserva) => $reserva->estado === EstadoReserva::ListaEspera)
            ->count();
    }

    /**
     * @param  Collection<int, Reserva>  $reservas
     */
    private function contarNoShow(Collection $reservas): int
    {
        return $reservas
            ->filter(fn (Reserva $reserva) => in_array($reserva->estado, EstadoReserva::noShow(), true))
            ->count();
    }

    /**
     * @param  Collection<int, Reserva>  $reservas
     */
    private function contarComensales(Collection $reservas): int
    {
        return (int) $reservas
            ->reject(fn (Reserva $reserva) => in_array($reserva->estado, EstadoReserva::noShow(), true))
            ->reject(fn (Reserva $reserva) => $reserva->estado === EstadoReserva::ListaEspera)
            ->sum('personas');
    }

    private function fechaDe(Reserva $reserva): string
    {
        return $reserva->fecha instanceof \DateTimeInterface
            ? $reserva->fecha->format('Y-m-d')
            : substr((string) $reserva->fecha, 0, 10);
    }
}
