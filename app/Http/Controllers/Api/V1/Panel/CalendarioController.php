<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Services\Panel\ReporteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Calendario del panel (docs §5.6): 42 celdas del mes y 7 de la semana.
 */
class CalendarioController extends Controller
{
    public function __construct(private readonly ReporteService $reportes) {}

    public function mes(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'mes' => ['required', 'integer', 'between:1,12'],
        ], [
            'anio.required' => 'Indica el año.',
            'anio.between' => 'El año no es válido.',
            'mes.required' => 'Indica el mes.',
            'mes.between' => 'El mes debe estar entre 1 y 12.',
        ]);

        return response()->json($this->reportes->calendarioMes((int) $datos['anio'], (int) $datos['mes']));
    }

    public function semana(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
        ], [
            'fecha.required' => 'Indica una fecha.',
            'fecha.date_format' => 'Elige una fecha válida.',
        ]);

        return response()->json($this->reportes->calendarioSemana($datos['fecha']));
    }
}
