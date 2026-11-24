<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalización de texto para búsquedas del panel: el filtro `texto` del
 * prototipo ignora mayúsculas y tildes (`coincideTexto` en src/lib/panel.js).
 */
final class Texto
{
    /**
     * Minúsculas, sin tildes y con espacios colapsados.
     */
    public static function normalizar(?string $valor): string
    {
        $limpio = Str::of((string) $valor)->lower()->replaceMatches('/\s+/u', ' ')->trim()->value();

        return strtr($limpio, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);
    }

    /**
     * Valor listo para un filtro SQL `LIKE %...%` insensible a tildes.
     */
    public static function patron(?string $valor): string
    {
        return '%'.self::normalizar($valor).'%';
    }
}
