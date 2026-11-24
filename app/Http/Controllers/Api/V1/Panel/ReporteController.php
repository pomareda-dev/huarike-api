<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Services\Panel\ReporteService;
use App\Support\Fechas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KPIs del panel (docs §5.5).
 */
class ReporteController extends Controller
{
    public function __construct(private readonly ReporteService $reportes) {}

    /**
     * Métricas del día indicado (por defecto, hoy).
     */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['sometimes', 'date_format:Y-m-d'],
        ], [
            'fecha.date_format' => 'Elige una fecha válida.',
        ]);

        $fecha = $datos['fecha'] ?? Fechas::hoy()->toDateString();

        return response()->json($this->reportes->delDia($fecha));
    }

    /**
     * Serie diaria para el gráfico de barras.
     */
    public function rango(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ], [
            'desde.required' => 'Elige la fecha de inicio.',
            'hasta.required' => 'Elige la fecha de fin.',
            'hasta.after_or_equal' => 'La fecha de fin debe ser posterior a la de inicio.',
        ]);

        $maximo = (int) config('huarike.reportes_rango_maximo_dias', 366);
        $dias = Fechas::carbon($datos['desde'])->diffInDays(Fechas::carbon($datos['hasta'])) + 1;

        if ($dias > $maximo) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos',
                'errors' => ['hasta' => ["El rango no puede superar {$maximo} días."]],
            ], 422);
        }

        return response()->json($this->reportes->rango($datos['desde'], $datos['hasta']));
    }
}
