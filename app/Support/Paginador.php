<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Construye el sobre de paginación que consume `Paginacion.jsx` del SPA:
 * `{ data, meta: { pagina, por_pagina, total, total_paginas, desde, hasta } }`
 * (`paginar` en src/lib/panel.js).
 */
final class Paginador
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  array<string, mixed>  $resumen  Datos extra del pie de tabla.
     */
    public static function respuesta(LengthAwarePaginator $paginator, array $resumen = []): JsonResponse
    {
        $items = Collection::make($paginator->items())
            ->map(fn ($item) => $item instanceof JsonResource
                ? $item->resolve()
                : (is_array($item) ? $item : $item->toArray()));

        $total = $paginator->total();
        $perPage = $paginator->perPage();
        $pagina = $paginator->currentPage();

        $meta = [
            'pagina' => $pagina,
            'por_pagina' => $perPage,
            'total' => $total,
            'total_paginas' => max(1, (int) ceil($total / max(1, $perPage))),
            'desde' => $total === 0 ? 0 : ($pagina - 1) * $perPage + 1,
            'hasta' => min($pagina * $perPage, $total),
        ];

        if ($resumen !== []) {
            $meta['resumen'] = $resumen;
        }

        return response()->json([
            'data' => $items->values()->all(),
            'meta' => $meta,
        ]);
    }

    /**
     * Parámetro `page` saneado, 1 por defecto.
     */
    public static function pagina(Request $request): int
    {
        $pagina = (int) $request->query('page', 1);

        return $pagina >= 1 ? $pagina : 1;
    }

    /**
     * Items por página del panel (§5: 6 por defecto).
     */
    public static function porPagina(): int
    {
        return (int) config('huarike.paginacion', 6);
    }
}
