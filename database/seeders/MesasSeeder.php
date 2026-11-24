<?php

namespace Database\Seeders;

use App\Models\Mesa;
use Illuminate\Database\Seeder;

/**
 * Las 12 mesas del prototipo, 38 asientos (`MESAS` en src/lib/reservas.js).
 */
class MesasSeeder extends Seeder
{
    /**
     * @var list<array{numero: int, zona: string, capacidad: int}>
     */
    public const MESAS = [
        ['numero' => 1, 'zona' => 'Salón', 'capacidad' => 2],
        ['numero' => 2, 'zona' => 'Terraza', 'capacidad' => 4],
        ['numero' => 3, 'zona' => 'Salón', 'capacidad' => 6],
        ['numero' => 4, 'zona' => 'Salón', 'capacidad' => 4],
        ['numero' => 5, 'zona' => 'Terraza', 'capacidad' => 4],
        ['numero' => 6, 'zona' => 'Salón', 'capacidad' => 6],
        ['numero' => 7, 'zona' => 'Terraza', 'capacidad' => 2],
        ['numero' => 8, 'zona' => 'Salón', 'capacidad' => 2],
        ['numero' => 9, 'zona' => 'Salón', 'capacidad' => 2],
        ['numero' => 10, 'zona' => 'Terraza', 'capacidad' => 2],
        ['numero' => 11, 'zona' => 'Salón', 'capacidad' => 2],
        ['numero' => 12, 'zona' => 'Terraza', 'capacidad' => 2],
    ];

    public function run(): void
    {
        foreach (self::MESAS as $mesa) {
            Mesa::withTrashed()->updateOrCreate(
                ['numero' => $mesa['numero']],
                [
                    'zona' => $mesa['zona'],
                    'capacidad' => $mesa['capacidad'],
                    'activa' => true,
                    'deleted_at' => null,
                ],
            );
        }
    }
}
