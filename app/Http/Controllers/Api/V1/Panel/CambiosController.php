<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Models\Mesa;
use App\Models\Reserva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Polling del panel (docs §8.2): el SPA consulta cada 30 s y refresca solo si
 * la respuesta trae cambios.
 */
class CambiosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'desde' => ['required', 'date'],
        ], [
            'desde.required' => 'Indica desde cuándo buscar cambios.',
            'desde.date' => 'La marca de tiempo de inicio no es válida.',
        ]);

        $desde = Carbon::parse($datos['desde'], config('huarike.zona_horaria'));

        $reservas = Reserva::query()
            ->where('updated_at', '>', $desde)
            ->orderBy('updated_at')
            ->limit(200)
            ->get()
            ->map(fn (Reserva $reserva) => [
                'tipo' => 'reserva',
                'id' => $reserva->id,
                'folio' => $reserva->folio,
                'actualizado_en' => $reserva->updated_at?->toIso8601String(),
            ]);

        $mesas = Mesa::query()
            ->where('updated_at', '>', $desde)
            ->orderBy('updated_at')
            ->limit(200)
            ->get()
            ->map(fn (Mesa $mesa) => [
                'tipo' => 'mesa',
                'id' => $mesa->id,
                'folio' => null,
                'numero' => $mesa->numero,
                'actualizado_en' => $mesa->updated_at?->toIso8601String(),
            ]);

        return response()->json([
            'desde' => $desde->toIso8601String(),
            'cambios' => $reservas->concat($mesas)->sortBy('actualizado_en')->values()->all(),
        ]);
    }
}
