<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ActualizarMesaRequest;
use App\Http\Requests\Panel\GuardarMesaRequest;
use App\Http\Resources\MesaResource;
use App\Models\Mesa;
use App\Services\Mesas\MesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mesas del salón en el panel (docs §5.2).
 */
class MesaController extends Controller
{
    public function __construct(private readonly MesaService $mesas) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'mesas' => MesaResource::collection(Mesa::query()->orderBy('numero')->get())->resolve(),
            'asientos_activos' => $this->mesas->asientosActivos(),
        ]);
    }

    public function store(GuardarMesaRequest $request): JsonResponse
    {
        $mesa = $this->mesas->crear([
            'zona' => $request->validated('zona'),
            'capacidad' => (int) $request->validated('capacidad'),
        ]);

        return (new MesaResource($mesa))->response()->setStatusCode(201);
    }

    public function update(ActualizarMesaRequest $request, Mesa $mesa): MesaResource
    {
        $actualizada = $this->mesas->actualizar($mesa, [
            'zona' => $request->validated('zona'),
            'capacidad' => (int) $request->validated('capacidad'),
            'activa' => $request->has('activa') ? $request->boolean('activa') : null,
        ]);

        return new MesaResource($actualizada);
    }

    public function activar(Request $request, Mesa $mesa): MesaResource
    {
        return new MesaResource($this->mesas->activar($mesa));
    }

    public function desactivar(Request $request, Mesa $mesa): MesaResource
    {
        return new MesaResource($this->mesas->desactivar($mesa));
    }

    public function destroy(Request $request, Mesa $mesa): JsonResponse
    {
        $this->mesas->eliminar($mesa);

        return response()->json(['message' => 'Mesa eliminada.']);
    }
}
