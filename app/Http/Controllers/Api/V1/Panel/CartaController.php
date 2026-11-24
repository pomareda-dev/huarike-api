<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoriaResource;
use App\Services\Carta\CartaService;
use Illuminate\Http\JsonResponse;

/**
 * Carta completa para el panel: incluye categorías inactivas y platos agotados.
 */
class CartaController extends Controller
{
    public function __construct(private readonly CartaService $carta) {}

    public function index(): JsonResponse
    {
        $datos = $this->carta->panel();

        return response()->json([
            'resumen' => $datos['resumen'],
            'categorias' => CategoriaResource::collection($datos['categorias'])->resolve(),
        ]);
    }
}
