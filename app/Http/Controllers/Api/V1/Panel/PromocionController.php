<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ActualizarPromocionRequest;
use App\Http\Requests\Panel\GuardarPromocionRequest;
use App\Http\Requests\Panel\SubirFotoRequest;
use App\Http\Resources\PromocionResource;
use App\Models\Promocion;
use App\Services\Archivos\FotoService;
use App\Services\Panel\PromocionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD de promociones y upload de su foto (docs §5.8, §8.3).
 */
class PromocionController extends Controller
{
    public function __construct(private readonly PromocionService $promociones) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'promociones' => PromocionResource::collection(
                Promocion::query()->orderByDesc('destacada')->orderBy('nombre')->get(),
            )->resolve(),
        ]);
    }

    public function store(GuardarPromocionRequest $request): JsonResponse
    {
        $promocion = $this->promociones->crear($request->validated());

        return (new PromocionResource($promocion))->response()->setStatusCode(201);
    }

    public function update(ActualizarPromocionRequest $request, Promocion $promocion): PromocionResource
    {
        return new PromocionResource($this->promociones->actualizar($promocion, $request->validated()));
    }

    public function destroy(Request $request, Promocion $promocion): JsonResponse
    {
        $this->promociones->eliminar($promocion);

        return response()->json(['message' => 'Promoción eliminada.']);
    }

    public function foto(SubirFotoRequest $request, Promocion $promocion): JsonResponse
    {
        $actualizada = $this->promociones->subirFoto($promocion, $request->file('foto'));

        return response()->json([
            'foto' => FotoService::url($actualizada->foto),
        ]);
    }
}
