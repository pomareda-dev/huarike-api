<?php

namespace App\Services\Carta;

use App\Models\Categoria;
use App\Models\Plato;

/**
 * Carta del sitio público y del panel (docs §5.1).
 */
class CartaService
{
    /**
     * Carta pública: solo categorías activas, con sus platos y su bandera de
     * disponibilidad. El encabezado replica `contarCarta`.
     *
     * @return array{resumen: array<string, int>, categorias: list<Categoria>}
     */
    public function publica(): array
    {
        $categorias = Categoria::query()
            ->activas()
            ->ordenadas()
            ->with(['platos' => fn ($query) => $query->orderBy('nombre')])
            ->get();

        $platos = $categorias->flatMap(fn (Categoria $categoria) => $categoria->platos);
        $disponibles = $platos->where('disponible', true)->count();

        return [
            'resumen' => [
                'categorias' => $categorias->count(),
                'platos' => $platos->count(),
                'disponibles' => $disponibles,
                'agotados' => $platos->count() - $disponibles,
            ],
            'categorias' => $categorias->all(),
        ];
    }

    /**
     * Carta del panel: incluye categorías inactivas y platos agotados.
     *
     * @return array{resumen: array<string, int>, categorias: list<Categoria>}
     */
    public function panel(): array
    {
        $categorias = Categoria::query()
            ->ordenadas()
            ->with(['platos' => fn ($query) => $query->orderBy('nombre')])
            ->get();

        $platos = $categorias->flatMap(fn (Categoria $categoria) => $categoria->platos);
        $disponibles = $platos->where('disponible', true)->count();

        return [
            'resumen' => [
                'categorias' => $categorias->count(),
                'platos' => $platos->count(),
                'disponibles' => $disponibles,
                'agotados' => $platos->count() - $disponibles,
            ],
            'categorias' => $categorias->all(),
        ];
    }

    /**
     * Platos disponibles de una lista de ids, con su categoría, para armar la
     * preorden de una reserva (fase 3).
     *
     * @param  list<int>  $ids
     */
    public function platosParaPreorden(array $ids): array
    {
        return Plato::query()
            ->disponibles()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id')
            ->all();
    }
}
