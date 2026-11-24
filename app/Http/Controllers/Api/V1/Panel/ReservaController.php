<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Enums\EstadoReserva;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ActualizarReservaAdminRequest;
use App\Http\Requests\Panel\CambiarEstadoReservaRequest;
use App\Http\Requests\Panel\GuardarReservaAdminRequest;
use App\Http\Resources\ReservaResource;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Panel\ReservaAdminService;
use App\Services\Panel\ReservaPanelService;
use App\Support\Csv;
use App\Support\Paginador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reservas desde el panel (docs §5.4): listado filtrado, alta, edición,
 * cambio de estado y export CSV.
 */
class ReservaController extends Controller
{
    public function __construct(
        private readonly ReservaPanelService $listado,
        private readonly ReservaAdminService $reservas,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $consulta = $this->listado->consulta($request);
        $resumen = $this->listado->resumen(clone $consulta);

        $paginator = $consulta
            ->orderBy('hora')
            ->orderBy('nombre')
            ->paginate(Paginador::porPagina(), ['*'], 'page', Paginador::pagina($request))
            ->through(fn (Reserva $reserva) => (new ReservaResource($reserva))->resolve());

        return Paginador::respuesta($paginator, $resumen);
    }

    public function store(GuardarReservaAdminRequest $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $reserva = $this->reservas->crear($admin, $request->validated());

        return (new ReservaResource($reserva))->response()->setStatusCode(201);
    }

    public function show(string $folio): ReservaResource
    {
        return new ReservaResource($this->buscar($folio));
    }

    public function update(ActualizarReservaAdminRequest $request, string $folio): ReservaResource
    {
        /** @var User $admin */
        $admin = $request->user();

        return new ReservaResource(
            $this->reservas->actualizar($this->buscar($folio), $request->validated(), $admin),
        );
    }

    public function estado(CambiarEstadoReservaRequest $request, string $folio): ReservaResource
    {
        /** @var User $admin */
        $admin = $request->user();

        return new ReservaResource(
            $this->reservas->cambiarEstado(
                $this->buscar($folio),
                EstadoReserva::from($request->validated('estado')),
                $admin,
            ),
        );
    }

    /**
     * CSV con las mismas columnas que el prototipo (`csvDeReservas`).
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $filas = $this->listado->consulta($request)
            ->orderBy('fecha')
            ->orderBy('hora')
            ->cursor()
            ->map(fn (Reserva $reserva) => [
                $reserva->folio,
                $reserva->fecha instanceof \DateTimeInterface
                    ? $reserva->fecha->format('Y-m-d')
                    : substr((string) $reserva->fecha, 0, 10),
                $reserva->hora,
                $reserva->nombre,
                $reserva->telefono,
                $reserva->correo,
                $reserva->personas,
                $reserva->mesa_id === null ? 'Sin mesa' : "Mesa {$reserva->mesa?->numero}",
                $reserva->mesa?->zona?->value ?? '',
                $reserva->estado->etiqueta(),
            ]);

        return Csv::descargar('reservas.csv', [
            'Folio', 'Fecha', 'Hora', 'Cliente', 'Teléfono', 'Correo',
            'Personas', 'Mesa', 'Zona', 'Estado',
        ], $filas);
    }

    private function buscar(string $folio): Reserva
    {
        return Reserva::query()
            ->where('folio', $folio)
            ->with(['mesa', 'platos', 'historial'])
            ->firstOrFail();
    }
}
