<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ActualizarHorarioRequest;
use App\Http\Requests\Panel\GuardarHorarioRequest;
use App\Http\Resources\HorarioResource;
use App\Models\Horario;
use App\Services\Panel\HorarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Franjas de atención editables (docs §5.9).
 */
class HorarioController extends Controller
{
    public function __construct(private readonly HorarioService $horarios) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'horarios' => HorarioResource::collection(
                Horario::query()->orderBy('abre')->get(),
            )->resolve(),
        ]);
    }

    public function store(GuardarHorarioRequest $request): JsonResponse
    {
        $horario = $this->horarios->crearHorario($request->validated());

        return (new HorarioResource($horario))->response()->setStatusCode(201);
    }

    public function update(ActualizarHorarioRequest $request, Horario $horario): HorarioResource
    {
        return new HorarioResource($this->horarios->actualizarHorario($horario, $request->validated()));
    }

    public function destroy(Request $request, Horario $horario): JsonResponse
    {
        $this->horarios->eliminarHorario($horario);

        return response()->json(['message' => 'Franja eliminada.']);
    }
}
