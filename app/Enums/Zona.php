<?php

namespace App\Enums;

/**
 * Zonas físicas del salón. Conjunto cerrado: `ZONAS` en src/lib/mesas.js.
 */
enum Zona: string
{
    case Salon = 'Salón';
    case Terraza = 'Terraza';

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $zona) => $zona->value, self::cases());
    }
}
