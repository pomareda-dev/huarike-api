<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarReservaRequest;
use App\Http\Resources\ReservaResource;
use App\Models\Reserva;
use App\Models\User;
use App\Services\Reservas\ReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Flujo de reserva del cliente (docs §5.3).
 */
class ReservaController extends Controller
{
    public function __construct(private readonly ReservaService $reservas) {}

    public function store(GuardarReservaRequest $request): JsonResponse
    {
        /** @var User $cliente */
        $cliente = $request->user();

        $reserva = $this->reservas->crear($cliente, $request->validated());

        return (new ReservaResource($reserva))->response()->setStatusCode(201);
    }

    /**
     * `{ proximas, pasadas }`, sin canceladas.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $cliente */
        $cliente = $request->user();

        $datos = $this->reservas->deLaCuenta($cliente);

        return response()->json([
            'proximas' => ReservaResource::collection($datos['proximas'])->resolve(),
            'pasadas' => ReservaResource::collection($datos['pasadas'])->resolve(),
        ]);
    }

    public function show(Request $request, string $folio): ReservaResource
    {
        $reserva = $this->buscar($folio);

        $this->authorize('view', $reserva);

        return new ReservaResource($reserva->load(['mesa', 'platos', 'historial']));
    }

    public function cancelar(Request $request, string $folio): ReservaResource
    {
        $reserva = $this->buscar($folio);

        $this->authorize('cancel', $reserva);

        /** @var User $actor */
        $actor = $request->user();

        return new ReservaResource(
            $this->reservas->cancelar($reserva, $actor, $actor->esAdmin()),
        );
    }

    /**
     * Las rutas públicas usan folio, nunca id (docs §11).
     */
    private function buscar(string $folio): Reserva
    {
        return Reserva::query()
            ->where('folio', $folio)
            ->with(['mesa', 'platos', 'historial'])
            ->firstOrFail();
    }
}
