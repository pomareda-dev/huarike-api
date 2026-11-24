<?php

namespace App\Enums;

/**
 * Categorías del filtro público de promociones (`FILTROS_PROMOCIONES` en
 * src/content/sitio.js).
 */
enum CategoriaPromocion: string
{
    case Desayunos = 'desayunos';
    case Almuerzos = 'almuerzos';
    case Familia = 'familia';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $categoria) => $categoria->value, self::cases());
    }
}
