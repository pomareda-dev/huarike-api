<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ActualizarPlatoRequest;
use App\Http\Requests\Panel\GuardarPlatoRequest;
use App\Http\Requests\Panel\SubirFotoRequest;
use App\Http\Resources\PlatoResource;
use App\Models\Plato;
use App\Services\Archivos\FotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD de platos (docs §5.1). El nombre es único dentro de su categoría y el
 * plato se elimina con soft-delete para no romper preórdenes históricas.
 */
class PlatoController extends Controller
{
    public function __construct(private readonly FotoService $fotos) {}

    public function store(GuardarPlatoRequest $request): JsonResponse
    {
        $plato = Plato::create([
            'categoria_id' => $request->validated('categoria_id'),
            'nombre' => $request->validated('nombre'),
            'descripcion' => $request->validated('descripcion'),
            'precio' => $request->validated('precio'),
            'etiquetas' => $request->validated('etiquetas') ?? [],
            'disponible' => $request->boolean('disponible', true),
        ]);

        return (new PlatoResource($plato))->response()->setStatusCode(201);
    }

    public function update(ActualizarPlatoRequest $request, Plato $plato): PlatoResource
    {
        $plato->fill([
            'categoria_id' => $request->validated('categoria_id'),
            'nombre' => $request->validated('nombre'),
            'descripcion' => $request->validated('descripcion'),
            'precio' => $request->validated('precio'),
            'etiquetas' => $request->validated('etiquetas') ?? $plato->etiquetas ?? [],
            'disponible' => $request->has('disponible') ? $request->boolean('disponible') : $plato->disponible,
        ])->save();

        return new PlatoResource($plato);
    }

    public function destroy(Request $request, Plato $plato): JsonResponse
    {
        $this->fotos->eliminar($plato->foto);
        $plato->delete();

        return response()->json(['message' => 'Plato eliminado.']);
    }

    /**
     * Upload multipart de la foto del plato (docs §8.3).
     */
    public function foto(SubirFotoRequest $request, Plato $plato): JsonResponse
    {
        $anterior = $plato->foto;
        $ruta = $this->fotos->guardar($request->file('foto'), 'platos');

        $plato->forceFill(['foto' => $ruta])->save();
        $this->fotos->eliminar($anterior);

        return response()->json(['foto' => FotoService::url($ruta)]);
    }
}
