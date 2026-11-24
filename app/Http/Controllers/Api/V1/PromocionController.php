<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromocionResource;
use App\Models\Promocion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Promociones públicas (docs §5.8): solo activas, con filtro por categoría.
 */
class PromocionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categoria = $request->query('categoria');

        $promociones = Promocion::query()
            ->activas()
            ->when(
                is_string($categoria) && in_array($categoria, config('huarike.filtros_promociones', []), true),
                fn ($query) => $query->where('categoria', $categoria),
            )
            ->orderByDesc('destacada')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'filtros' => config('huarike.filtros_promociones', []),
            'promociones' => PromocionResource::collection($promociones)->resolve(),
        ]);
    }
}
