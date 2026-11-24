<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Cuentas de demostración del prototipo (`CUENTA_ADMIN_DEMO` y `CUENTA_DEMO`).
 * Las credenciales quedan documentadas en el README.
 */
class CuentasSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['correo' => 'ana.quispe@elhuarike.pe'],
            [
                'nombre' => 'Ana Quispe',
                'telefono' => '+51 999 111 222',
                'password' => 'panel2026',
                'rol' => Rol::Admin,
                'activa' => true,
            ],
        );

        User::updateOrCreate(
            ['correo' => 'demo@elhuarike.com'],
            [
                'nombre' => 'Lucía Ramírez',
                'telefono' => '+51 987 654 321',
                'password' => 'reservas2024',
                'rol' => Rol::Cliente,
                'activa' => true,
            ],
        );
    }
}
