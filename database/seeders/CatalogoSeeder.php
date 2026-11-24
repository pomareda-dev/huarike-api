<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Plato;
use Illuminate\Database\Seeder;

/**
 * Carta completa del prototipo (`CATEGORIAS_CARTA` en src/content/sitio.js):
 * 8 categorías y 22 platos con sus precios, etiquetas y disponibilidad.
 */
class CatalogoSeeder extends Seeder
{
    /**
     * @var list<array{nombre: string, platos: list<array<string, mixed>>}>
     */
    public const CARTA = [
        [
            'nombre' => 'Cebiches y tiraditos',
            'platos' => [
                [
                    'nombre' => 'Cebiche clásico',
                    'descripcion' => 'Delicioso y fresco pescado preparado de la forma tradicional con el toque de El Huarike.',
                    'precio' => 45.00,
                    'etiquetas' => ['Más pedido'],
                ],
                [
                    'nombre' => 'Cebiche mixto',
                    'descripcion' => 'Pescado, calamar, conchas y langostinos en leche de tigre al punto.',
                    'precio' => 52.00,
                    'etiquetas' => ['Más pedido'],
                ],
                [
                    'nombre' => 'Tiradito en ají amarillo',
                    'descripcion' => 'Láminas de pescado en crema de ají amarillo con choclo y camote.',
                    'precio' => 46.00,
                    'etiquetas' => ['Sin gluten', 'Picante suave'],
                ],
                [
                    'nombre' => 'Leche de tigre',
                    'descripcion' => 'Nuestro jugo de cebiche servido como entrada.',
                    'precio' => 22.00,
                    'etiquetas' => ['Para compartir'],
                ],
            ],
        ],
        [
            'nombre' => 'Piqueos',
            'platos' => [
                [
                    'nombre' => 'El marinero',
                    'descripcion' => 'Piqueo de cebiche mixto, causa Huarike, tiradito de pescado, chicharrón de calamar y pulpo al olivo.',
                    'precio' => 68.00,
                    'etiquetas' => ['Para compartir'],
                ],
                [
                    'nombre' => 'Causa crocante',
                    'descripcion' => 'Rellena con pulpa de cangrejo, palta y mayonesa, con salsa de maracuyá.',
                    'precio' => 32.00,
                    'etiquetas' => ['Más pedido'],
                ],
                [
                    'nombre' => 'Piqueo Huarike',
                    'descripcion' => 'Cuatro bocados de la casa: chicharrón de calamar, conchitas, causa y tiradito.',
                    'precio' => 54.00,
                    'etiquetas' => ['Para compartir'],
                ],
            ],
        ],
        [
            'nombre' => 'Arroces',
            'platos' => [
                [
                    'nombre' => 'Arroz con mariscos',
                    'descripcion' => 'Tradicional plato marino bañado en nuestra salsa secreta.',
                    'precio' => 48.00,
                    'etiquetas' => ['Sin gluten'],
                ],
                [
                    'nombre' => 'Arroz con pato',
                    'descripcion' => 'Arroz con culantro y pato de corral, cocinado a fuego lento.',
                    'precio' => 46.00,
                    'etiquetas' => [],
                ],
                [
                    'nombre' => 'Chaufa de mariscos',
                    'descripcion' => 'Arroz salteado al wok con mariscos y cebollita china.',
                    'precio' => 42.00,
                    'etiquetas' => [],
                    'disponible' => false,
                ],
            ],
        ],
        [
            'nombre' => 'Pescados',
            'platos' => [
                [
                    'nombre' => 'Pescado a lo macho',
                    'descripcion' => 'Filete de pescado con salsa de mariscos al ají panca y arroz blanco.',
                    'precio' => 58.00,
                    'etiquetas' => ['Picante medio'],
                ],
                [
                    'nombre' => 'Tacu tacu con pescado',
                    'descripcion' => 'Tacu tacu de frejol y arroz con filete de pescado a la plancha.',
                    'precio' => 46.00,
                    'etiquetas' => ['Sin gluten'],
                ],
                [
                    'nombre' => 'Chita frita',
                    'descripcion' => 'Pescado entero del día, frito y acompañado de yuca y sarza criolla.',
                    'precio' => 52.00,
                    'etiquetas' => ['Pescado del día'],
                ],
            ],
        ],
        [
            'nombre' => 'Mariscos',
            'platos' => [
                [
                    'nombre' => 'Pulpo al olivo',
                    'descripcion' => 'Pulpo tierno en crema de aceituna botija y limón.',
                    'precio' => 56.00,
                    'etiquetas' => ['Sin gluten'],
                ],
                [
                    'nombre' => 'Conchitas a la parmesana',
                    'descripcion' => 'Conchas al gratín con parmesano y un toque de limón.',
                    'precio' => 44.00,
                    'etiquetas' => ['Para compartir'],
                ],
                [
                    'nombre' => 'Jalea mixta',
                    'descripcion' => 'Pescado, calamar y langostinos crocantes con salsa criolla.',
                    'precio' => 62.00,
                    'etiquetas' => ['Para compartir'],
                ],
            ],
        ],
        [
            'nombre' => 'Causas',
            'platos' => [
                [
                    'nombre' => 'Causa de cangrejo',
                    'descripcion' => 'Papa amarilla con pulpa de cangrejo y palta.',
                    'precio' => 38.00,
                    'etiquetas' => ['Sin gluten'],
                ],
                [
                    'nombre' => 'Causa de pulpa de cangrejo y maracuyá',
                    'descripcion' => 'Nuestra causa insignia con salsa de maracuyá.',
                    'precio' => 36.00,
                    'etiquetas' => ['Más pedido'],
                ],
            ],
        ],
        [
            'nombre' => 'Postres',
            'platos' => [
                [
                    'nombre' => 'Suspiro limeño',
                    'descripcion' => 'Manjar blanco con merengue al oporto.',
                    'precio' => 18.00,
                    'etiquetas' => ['Vegetariano'],
                ],
                [
                    'nombre' => 'Picarones',
                    'descripcion' => 'Cuatro picarones con miel de chancaca.',
                    'precio' => 16.00,
                    'etiquetas' => ['Vegetariano'],
                ],
            ],
        ],
        [
            'nombre' => 'Bebidas',
            'platos' => [
                [
                    'nombre' => 'Chicha morada (jarra)',
                    'descripcion' => 'Preparada en casa, sin colorantes.',
                    'precio' => 18.00,
                    'etiquetas' => ['Vegetariano'],
                ],
                [
                    'nombre' => 'Limonada frozen',
                    'descripcion' => 'Limón, hierbabuena y hielo frappé.',
                    'precio' => 14.00,
                    'etiquetas' => ['Vegetariano'],
                ],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::CARTA as $posicion => $grupo) {
            $categoria = Categoria::updateOrCreate(
                ['nombre' => $grupo['nombre']],
                ['posicion' => $posicion + 1, 'activa' => true],
            );

            foreach ($grupo['platos'] as $plato) {
                Plato::withTrashed()->updateOrCreate(
                    [
                        'categoria_id' => $categoria->id,
                        'nombre' => $plato['nombre'],
                    ],
                    [
                        'descripcion' => $plato['descripcion'],
                        'precio' => $plato['precio'],
                        'etiquetas' => $plato['etiquetas'],
                        'disponible' => $plato['disponible'] ?? true,
                        'foto' => null,
                        'deleted_at' => null,
                    ],
                );
            }
        }
    }
}
