<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoriaResource;
use App\Http\Resources\HorarioResource;
use App\Http\Resources\TurnoResource;
use App\Models\Horario;
use App\Models\Turno;
use App\Services\Carta\CartaService;
use App\Services\Horarios\EstadoLocalService;
use Illuminate\Http\JsonResponse;

/**
 * Contenido público del sitio: carta, horarios con estado del local y turnos
 * de reserva (docs §5.1, §5.9).
 */
class CartaController extends Controller
{
    public function __construct(
        private readonly CartaService $carta,
        private readonly EstadoLocalService $estadoLocal,
    ) {}

    public function carta(): JsonResponse
    {
        $datos = $this->carta->publica();

        return response()->json([
            'resumen' => $datos['resumen'],
            'categorias' => CategoriaResource::collection($datos['categorias'])->resolve(),
        ]);
    }

    /**
     * Franjas de atención activas más el badge "Abierto ahora · cierra a las…".
     */
    public function horarios(): JsonResponse
    {
        $franjas = Horario::query()->activos()->orderBy('abre')->get();

        return response()->json([
            'estado' => $this->estadoLocal->estado(),
            'horarios' => HorarioResource::collection($franjas)->resolve(),
        ]);
    }

    /**
     * Turnos de reserva activos con sus slots vigentes.
     */
    public function turnos(): JsonResponse
    {
        $turnos = Turno::query()->activos()->orderBy('id')->get();

        return response()->json([
            'turnos' => TurnoResource::collection($turnos)->resolve(),
        ]);
    }
}
