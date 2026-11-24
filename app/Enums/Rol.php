<?php

namespace App\Enums;

/**
 * Roles disponibles en el sistema. No existe tabla de roles: es una columna
 * en `users`, igual que `ROLES` en el prototipo (src/lib/panel.js).
 */
enum Rol: string
{
    case Admin = 'admin';
    case Cliente = 'cliente';

    /**
     * Etiqueta legible usada en CSV y respuestas del panel.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Cliente => 'Cliente',
        };
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $rol) => $rol->value, self::cases());
    }
}
