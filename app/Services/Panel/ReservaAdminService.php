<?php

namespace App\Services\Panel;

use App\Enums\EstadoReserva;
use App\Exceptions\ReglaNegocioException;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\ReservaHistorial;
use App\Models\ReservaPlato;
use App\Models\User;
use App\Services\Reservas\DisponibilidadService;
use App\Services\Reservas\PreordenService;
use App\Services\Reservas\ReservaService;
use App\Support\Fechas;
use Illuminate\Support\Facades\DB;

/**
 * Alta, edición y cambio de estado de reservas desde el panel (docs §5.4).
 */
class ReservaAdminService
{
    public function __construct(
        private readonly DisponibilidadService $disponibilidad,
        private readonly PreordenService $preorden,
        private readonly ReservaService $reservas,
    ) {}

    /**
     * Alta desde el panel: la mesa es obligatoria y queda registrado el admin.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(User $admin, array $datos): Reserva
    {
        return DB::transaction(function () use ($admin, $datos): Reserva {
            $fecha = $datos['fecha'];
            $hora = substr($datos['hora'], 0, 5);
            $personas = (int) $datos['personas'];

            $this->verificarMesa((int) $datos['mesa_id'], $fecha, $hora, $personas);

            [$platos, $subtotal] = $this->preorden->preparar($datos['platos'] ?? []);

            $reserva = Reserva::create([
                'folio' => $this->reservas->siguienteFolio(),
                'user_id' => null,
                'nombre' => $datos['nombre'],
                'correo' => $datos['correo'],
                'telefono' => $datos['telefono'],
                'notas' => $datos['notas'] ?? null,
                'fecha' => $fecha,
                'hora' => $hora,
                'personas' => $personas,
                'mesa_id' => (int) $datos['mesa_id'],
                'estado' => EstadoReserva::from($datos['estado'] ?? EstadoReserva::Confirmada->value),
                'subtotal' => $subtotal,
                'creada_por' => $admin->nombre,
            ]);

            $this->guardarPlatos($reserva, $platos);

            $this->historial($reserva, 'creacion', "Reserva creada por {$admin->nombre}", $admin->nombre);

            return $reserva->load(['mesa', 'platos', 'historial']);
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Reserva $reserva, array $datos, User $admin): Reserva
    {
        return DB::transaction(function () use ($reserva, $datos, $admin): Reserva {
            $fecha = $datos['fecha'] ?? $this->fechaDe($reserva);
            $hora = substr((string) ($datos['hora'] ?? $reserva->hora), 0, 5);
            $personas = (int) ($datos['personas'] ?? $reserva->personas);
            $mesaId = (int) ($datos['mesa_id'] ?? $reserva->mesa_id);

            $this->verificarMesa($mesaId, $fecha, $hora, $personas, $reserva->id);

            $reserva->fill([
                'nombre' => $datos['nombre'] ?? $reserva->nombre,
                'correo' => $datos['correo'] ?? $reserva->correo,
                'telefono' => $datos['telefono'] ?? $reserva->telefono,
                'notas' => $datos['notas'] ?? $reserva->notas,
                'fecha' => $fecha,
                'hora' => $hora,
                'personas' => $personas,
                'mesa_id' => $mesaId,
            ])->save();

            if (array_key_exists('platos', $datos)) {
                [$platos, $subtotal] = $this->preorden->preparar($datos['platos'] ?? []);
                $reserva->platos()->delete();
                $this->guardarPlatos($reserva, $platos);
                $reserva->forceFill(['subtotal' => $subtotal])->save();
            }

            $this->historial(
                $reserva,
                'edicion',
                "Edición: {$fecha} {$hora}, {$personas} personas, mesa {$mesaId}",
                $admin->nombre,
            );

            return $reserva->load(['mesa', 'platos', 'historial']);
        });
    }

    /**
     * Cambio de estado con las reglas RA4.
     */
    public function cambiarEstado(Reserva $reserva, EstadoReserva $nuevo, User $admin): Reserva
    {
        if ($reserva->estado === EstadoReserva::Cancelada) {
            throw new ReglaNegocioException('Esta reserva ya está cancelada.');
        }

        if ($reserva->estado === $nuevo) {
            throw new ReglaNegocioException('Esa reserva ya está en ese estado.');
        }

        if ($this->esPasada($reserva) && ! in_array($nuevo, [EstadoReserva::Finalizada, EstadoReserva::NoShow], true)) {
            throw new ReglaNegocioException(
                'Una reserva pasada solo puede marcarse como finalizada o no-show.',
            );
        }

        return DB::transaction(function () use ($reserva, $nuevo, $admin): Reserva {
            $reserva->forceFill(['estado' => $nuevo])->save();

            $this->historial(
                $reserva,
                'estado',
                "{$nuevo->etiqueta()} por {$admin->nombre}",
                $admin->nombre,
            );

            return $reserva->load(['mesa', 'platos', 'historial']);
        });
    }

    public function esPasada(Reserva $reserva): bool
    {
        $inicio = Fechas::instante($this->fechaDe($reserva), (string) $reserva->hora);

        return $inicio->addMinutes((int) config('huarike.duracion_minutos', 90))->lessThan(Fechas::ahora());
    }

    /**
     * Mesa existente, activa, con capacidad y sin choque de 90 minutos.
     */
    private function verificarMesa(int $mesaId, string $fecha, string $hora, int $personas, ?int $ignorarReservaId = null): void
    {
        $mesa = Mesa::query()->whereKey($mesaId)->lockForUpdate()->first();

        if ($mesa === null) {
            throw new ReglaNegocioException('La mesa indicada no existe.', ['motivo' => 'mesa-inexistente']);
        }

        if (! $mesa->activa) {
            throw new ReglaNegocioException(
                'Esa mesa está desactivada. Elige otra.',
                ['motivo' => 'mesa-inactiva'],
            );
        }

        if ($mesa->capacidad < $personas) {
            throw new ReglaNegocioException(
                "La mesa {$mesa->numero} no tiene capacidad para {$personas} personas.",
                ['motivo' => 'capacidad'],
            );
        }

        $reservas = $this->disponibilidad->reservasDelDia($fecha, $ignorarReservaId);

        if ($this->disponibilidad->hayConflicto($reservas, $mesa->id, $hora, $ignorarReservaId)) {
            throw new ReglaNegocioException(
                'Esa mesa ya está ocupada en esa franja',
                ['motivo' => 'conflicto'],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $platos
     */
    private function guardarPlatos(Reserva $reserva, array $platos): void
    {
        foreach ($platos as $plato) {
            ReservaPlato::create([
                'reserva_id' => $reserva->id,
                'plato_id' => $plato['plato_id'],
                'nombre' => $plato['nombre'],
                'precio' => $plato['precio'],
                'cantidad' => $plato['cantidad'],
            ]);
        }
    }

    private function historial(Reserva $reserva, string $tipo, string $texto, string $autor): void
    {
        ReservaHistorial::create([
            'reserva_id' => $reserva->id,
            'tipo' => $tipo,
            'texto' => $texto,
            'autor' => $autor,
        ]);
    }

    private function fechaDe(Reserva $reserva): string
    {
        return $reserva->fecha instanceof \DateTimeInterface
            ? $reserva->fecha->format('Y-m-d')
            : substr((string) $reserva->fecha, 0, 10);
    }
}
