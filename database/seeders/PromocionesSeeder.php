<?php

namespace Database\Seeders;

use App\Models\Promocion;
use Illuminate\Database\Seeder;

/**
 * Promociones del sitio público (`PROMOCIONES` en src/content/sitio.js).
 *
 * Nota: el documento de requisitos menciona seis promociones, pero el
 * prototipo define cinco. Se siembran las cinco reales para no inventar
 * contenido comercial.
 */
class PromocionesSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    public const PROMOCIONES = [
        [
            'nombre' => 'Martes de cebiche 2×1',
            'slug' => 'martes-de-cebiche-2x1',
            'destacada' => true,
            'vigencia' => 'Vigente hasta el 31 de diciembre',
            'categoria' => 'almuerzos',
            'condiciones' => [
                '2 cebiches clásicos por persona',
                'No acumulable con otras promociones',
                'Válido con reserva previa',
            ],
            'codigo' => 'CEBICHE2X1',
            'precio_texto' => '2×1',
        ],
        [
            'nombre' => 'Almuerzo ejecutivo S/ 29',
            'slug' => 'almuerzo-ejecutivo-s-29',
            'destacada' => false,
            'vigencia' => 'Lunes a viernes',
            'categoria' => 'almuerzos',
            'condiciones' => ['No acumulable', 'No incluye postre'],
            'codigo' => 'EJECUTIVO29',
            'precio_texto' => 'S/ 29',
        ],
        [
            'nombre' => 'Domingo familiar S/ 149',
            'slug' => 'domingo-familiar-s-149',
            'destacada' => false,
            'vigencia' => 'Todos los domingos',
            'categoria' => 'familia',
            'condiciones' => [
                'Grupos de 4 personas',
                'Menores de 8 años sin costo',
                'Reserva previa obligatoria',
            ],
            'codigo' => 'FAMILIA149',
            'precio_texto' => 'S/ 149',
        ],
        [
            'nombre' => 'Desayuno criollo S/ 19',
            'slug' => 'desayuno-criollo-s-19',
            'destacada' => false,
            'vigencia' => 'Martes a domingo',
            'categoria' => 'desayunos',
            'condiciones' => ['Hasta las 12:00', 'No acumulable'],
            'codigo' => 'DESAYUNO19',
            'precio_texto' => 'S/ 19',
        ],
        [
            'nombre' => 'Cumpleaños en El Huarike',
            'slug' => 'cumpleanos-en-el-huarike',
            'destacada' => false,
            'vigencia' => 'Todo el año',
            'categoria' => 'familia',
            'condiciones' => [
                'Desde 6 personas',
                'Avisar 24 horas antes',
                'Sujeto a disponibilidad',
            ],
            'codigo' => 'CUMPLE',
            'precio_texto' => 'Cortesía',
        ],
    ];

    public function run(): void
    {
        foreach (self::PROMOCIONES as $promocion) {
            Promocion::withTrashed()->updateOrCreate(
                ['slug' => $promocion['slug']],
                $promocion + ['foto' => null, 'activa' => true, 'deleted_at' => null],
            );
        }
    }
}
