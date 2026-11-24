<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos base que cualquier entorno necesita. `DemoSeeder` agrega reservas
     * de demostración y solo debe correrse en desarrollo.
     */
    public function run(): void
    {
        $this->call([
            CuentasSeeder::class,
            MesasSeeder::class,
            CatalogoSeeder::class,
            PromocionesSeeder::class,
            HorariosTurnosSeeder::class,
        ]);
    }
}
