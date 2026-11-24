<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DisponibilidadRequest;
use App\Http\Resources\MesaResource;
use App\Models\Mesa;
use App\Rules\HoraDeTurno;
use App\Services\Reservas\DisponibilidadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Disponibilidad pública calculada solo con reservas reales (docs §5.2).
 */
class DisponibilidadController extends Controller
{
    public function __construct(private readonly DisponibilidadService $disponibilidad) {}

    /**
     * Mesas activas del salón: `id, numero, zona, capacidad`.
     */
    public function mesasActivas(): JsonResponse
    {
        return response()->json([
            'mesas' => MesaResource::collection(
                Mesa::query()->activas()->orderBy('numero')->get(),
            )->resolve(),
        ]);
    }

    /**
     * Estado de cada slot del día para el grupo indicado.
     */
    public function index(DisponibilidadRequest $request): JsonResponse
    {
        return response()->json(
            $this->disponibilidad->delDia(
                $request->validated('fecha'),
                (int) $request->validated('personas'),
            ),
        );
    }

    /**
     * Mesas concretas con capacidad suficiente y sin choque de 90 minutos.
     */
    public function mesas(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i', new HoraDeTurno],
            'personas' => [
                'required', 'integer',
                'between:'.config('huarike.personas.minimo', 1).','.config('huarike.personas.maximo', 6),
            ],
        ], [
            'fecha.required' => 'Elige la fecha de tu reserva.',
            'hora.required' => 'Elige el horario de tu reserva.',
            'hora.date_format' => 'Elige un horario de reserva disponible.',
            'personas.required' => 'Indica para cuántas personas es la reserva.',
            'personas.between' => 'La reserva es de 1 a 6 personas.',
        ]);

        $mesas = $this->disponibilidad->mesasPara(
            $datos['fecha'],
            substr($datos['hora'], 0, 5),
            (int) $datos['personas'],
        );

        return response()->json([
            'fecha' => $datos['fecha'],
            'hora' => substr($datos['hora'], 0, 5),
            'personas' => (int) $datos['personas'],
            'mesas' => MesaResource::collection($mesas)->resolve(),
        ]);
    }
}
