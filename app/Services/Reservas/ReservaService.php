<?php

namespace App\Services\Reservas;

use App\Enums\EstadoReserva;
use App\Exceptions\ReglaNegocioException;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\ReservaHistorial;
use App\Models\ReservaPlato;
use App\Models\User;
use App\Support\Fechas;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida de una reserva del cliente (docs §5.3): folio correlativo,
 * preorden con snapshot de precios, historial acumulativo y ventana de
 * cancelación de 2 horas.
 */
class ReservaService
{
    public function __construct(
        private readonly DisponibilidadService $disponibilidad,
        private readonly PreordenService $preorden,
    ) {}

    /**
     * @param  array{fecha: string, hora: string, personas: int, mesa_id?: int|null, notas?: string|null, platos?: list<array{plato_id: int, cantidad: int}>}  $datos
     */
    public function crear(User $cliente, array $datos, ?string $creadaPor = null): Reserva
    {
        $fecha = $datos['fecha'];
        $hora = substr($datos['hora'], 0, 5);
        $personas = (int) $datos['personas'];
        $mesaId = isset($datos['mesa_id']) ? (int) $datos['mesa_id'] : null;

        return DB::transaction(function () use ($cliente, $datos, $fecha, $hora, $personas, $mesaId, $creadaPor): Reserva {
            if ($mesaId !== null) {
                $this->verificarMesa($mesaId, $fecha, $hora, $personas);
            }

            [$platos, $subtotal] = $this->preorden->preparar($datos['platos'] ?? []);

            $reserva = Reserva::create([
                'folio' => $this->siguienteFolio(),
                'user_id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'correo' => $cliente->correo,
                'telefono' => $cliente->telefono,
                'notas' => $datos['notas'] ?? null,
                'fecha' => $fecha,
                'hora' => $hora,
                'personas' => $personas,
                'mesa_id' => $mesaId,
                // Sin mesa la reserva entra a la lista de espera.
                'estado' => $mesaId === null ? EstadoReserva::ListaEspera : EstadoReserva::Confirmada,
                'subtotal' => $subtotal,
                'creada_por' => $creadaPor,
            ]);

            foreach ($platos as $plato) {
                ReservaPlato::create([
                    'reserva_id' => $reserva->id,
                    'plato_id' => $plato['plato_id'],
                    'nombre' => $plato['nombre'],
                    'precio' => $plato['precio'],
                    'cantidad' => $plato['cantidad'],
                ]);
            }

            $this->registrarHistorial(
                $reserva,
                'creacion',
                $creadaPor === null ? 'Reserva creada por el cliente' : "Reserva creada por {$creadaPor}",
                $creadaPor ?? $cliente->nombre,
            );

            return $reserva->load(['mesa', 'platos', 'historial']);
        });
    }

    /**
     * Cancela dentro de la ventana permitida y deja rastro en el historial.
     */
    public function cancelar(Reserva $reserva, User $actor, bool $comoAdmin = false): Reserva
    {
        if ($reserva->estado === EstadoReserva::Cancelada) {
            throw new ReglaNegocioException('Esta reserva ya está cancelada.');
        }

        $inicio = Fechas::instante(
            $reserva->fecha instanceof \DateTimeInterface ? $reserva->fecha->format('Y-m-d') : substr((string) $reserva->fecha, 0, 10),
            (string) $reserva->hora,
        );

        $horas = (int) config('huarike.ventana_cancelacion_horas', 2);

        if (! $comoAdmin && Fechas::ahora()->greaterThan($inicio->subHours($horas))) {
            throw new ReglaNegocioException("Solo puedes cancelar hasta {$horas} horas antes.");
        }

        return DB::transaction(function () use ($reserva, $actor, $comoAdmin): Reserva {
            $reserva->forceFill(['estado' => EstadoReserva::Cancelada])->save();

            $this->registrarHistorial(
                $reserva,
                'estado',
                $comoAdmin ? "Cancelada por {$actor->nombre}" : 'Cancelada por el cliente',
                $actor->nombre,
            );

            return $reserva->load(['mesa', 'platos', 'historial']);
        });
    }

    /**
     * Reservas de una cuenta separadas en próximas y pasadas, sin canceladas.
     *
     * @return array{proximas: list<Reserva>, pasadas: list<Reserva>}
     */
    public function deLaCuenta(User $cliente): array
    {
        $ahora = Fechas::ahora();

        $reservas = Reserva::query()
            ->where('correo', $cliente->correo)
            ->whereNot('estado', EstadoReserva::Cancelada->value)
            ->with(['mesa', 'platos', 'historial'])
            ->get();

        $esPasada = fn (Reserva $reserva): bool => Fechas::instante(
            $reserva->fecha instanceof \DateTimeInterface ? $reserva->fecha->format('Y-m-d') : substr((string) $reserva->fecha, 0, 10),
            (string) $reserva->hora,
        )->addMinutes((int) config('huarike.duracion_minutos', 90))->lessThan($ahora);

        [$pasadas, $proximas] = $reservas->partition($esPasada);

        return [
            'proximas' => $proximas->sortBy(fn (Reserva $r) => substr((string) $r->fecha, 0, 10).' '.$r->hora)->values()->all(),
            'pasadas' => $pasadas->sortByDesc(fn (Reserva $r) => substr((string) $r->fecha, 0, 10).' '.$r->hora)->values()->all(),
        ];
    }

    /**
     * Folio consecutivo con el mismo piso que el prototipo (HRK-2481…).
     */
    public function siguienteFolio(): string
    {
        // El lock sobre la última reserva serializa la generación del folio.
        Reserva::query()->orderByDesc('id')->lockForUpdate()->first();

        $mayor = 2480;

        foreach (Reserva::query()->pluck('folio') as $folio) {
            $numero = (int) substr((string) $folio, 4);

            if ($numero > $mayor) {
                $mayor = $numero;
            }
        }

        return 'HRK-'.($mayor + 1);
    }

    /**
     * Verifica mesa activa, capacidad y choque de 90 minutos con lock de fila.
     */
    private function verificarMesa(int $mesaId, string $fecha, string $hora, int $personas): void
    {
        $mesa = Mesa::query()->whereKey($mesaId)->lockForUpdate()->first();

        if ($mesa === null || ! $mesa->activa) {
            throw new ReglaNegocioException(
                'Esa mesa no está disponible. Elige otra.',
                ['motivo' => 'mesa-inactiva', 'mesas' => $this->mesasValidas($fecha, $hora, $personas)],
            );
        }

        if ($mesa->capacidad < $personas) {
            throw new ReglaNegocioException(
                "La mesa {$mesa->numero} no tiene capacidad para {$personas} personas.",
                ['motivo' => 'capacidad', 'mesas' => $this->mesasValidas($fecha, $hora, $personas)],
            );
        }

        $reservas = $this->disponibilidad->reservasDelDia($fecha);

        if ($this->disponibilidad->hayConflicto($reservas, $mesa->id, $hora)) {
            throw new ReglaNegocioException(
                'Esa mesa ya está ocupada en esa franja',
                ['motivo' => 'conflicto', 'mesas' => $this->mesasValidas($fecha, $hora, $personas)],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mesasValidas(string $fecha, string $hora, int $personas): array
    {
        return $this->disponibilidad->mesasPara($fecha, $hora, $personas)
            ->map(fn ($mesa) => [
                'id' => $mesa->id,
                'numero' => $mesa->numero,
                'zona' => $mesa->zona->value,
                'capacidad' => $mesa->capacidad,
            ])
            ->values()
            ->all();
    }

    private function registrarHistorial(Reserva $reserva, string $tipo, string $texto, string $autor): void
    {
        ReservaHistorial::create([
            'reserva_id' => $reserva->id,
            'tipo' => $tipo,
            'texto' => $texto,
            'autor' => $autor,
        ]);
    }
}
