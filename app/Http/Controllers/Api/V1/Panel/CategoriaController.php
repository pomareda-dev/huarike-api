<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ActualizarCategoriaRequest;
use App\Http\Requests\Panel\GuardarCategoriaRequest;
use App\Http\Resources\CategoriaResource;
use App\Models\Categoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD de categorías de la carta (docs §5.1).
 */
class CategoriaController extends Controller
{
    public function store(GuardarCategoriaRequest $request): JsonResponse
    {
        $categoria = Categoria::create([
            'nombre' => $request->validated('nombre'),
            'posicion' => $request->validated('posicion') ?? ((int) Categoria::query()->max('posicion')) + 1,
            'activa' => $request->boolean('activa', true),
        ]);

        return (new CategoriaResource($categoria->load('platos')))->response()->setStatusCode(201);
    }

    public function update(ActualizarCategoriaRequest $request, Categoria $categoria): CategoriaResource
    {
        $categoria->fill([
            'nombre' => $request->validated('nombre'),
            'posicion' => $request->validated('posicion') ?? $categoria->posicion,
            'activa' => $request->has('activa') ? $request->boolean('activa') : $categoria->activa,
        ])->save();

        return new CategoriaResource($categoria->load('platos'));
    }

    /**
     * Soft-delete: se rechaza mientras la categoría tenga platos activos.
     */
    public function destroy(Request $request, Categoria $categoria): JsonResponse
    {
        $platosActivos = $categoria->platos()->where('disponible', true)->count();

        if ($platosActivos > 0) {
            throw new ReglaNegocioException(
                'No puedes eliminar una categoría con platos activos. Desactívala primero.',
                ['motivo' => 'categoria-con-platos'],
            );
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada.']);
    }
}
