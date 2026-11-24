<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ActualizarTurnoRequest;
use App\Http\Requests\Panel\GuardarTurnoRequest;
use App\Http\Resources\TurnoResource;
use App\Models\Turno;
use App\Services\Panel\HorarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Turnos de reserva editables (docs §5.9). La disponibilidad, la validación
 * de hora y los filtros del panel leen esta tabla en tiempo real.
 */
class TurnoController extends Controller
{
    public function __construct(private readonly HorarioService $horarios) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'turnos' => TurnoResource::collection(
                Turno::query()->orderBy('id')->get(),
            )->resolve(),
        ]);
    }

    public function store(GuardarTurnoRequest $request): JsonResponse
    {
        $turno = $this->horarios->crearTurno($request->validated());

        return (new TurnoResource($turno))->response()->setStatusCode(201);
    }

    public function update(ActualizarTurnoRequest $request, Turno $turno): TurnoResource
    {
        return new TurnoResource($this->horarios->actualizarTurno($turno, $request->validated()));
    }

    public function destroy(Request $request, Turno $turno): JsonResponse
    {
        $this->horarios->eliminarTurno($turno);

        return response()->json(['message' => 'Turno eliminado.']);
    }
}
