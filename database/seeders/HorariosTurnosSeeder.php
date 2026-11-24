<?php

namespace Database\Seeders;

use App\Models\Horario;
use App\Models\Turno;
use Illuminate\Database\Seeder;

/**
 * Franjas de atención (`HORARIOS` en src/lib/horario.js) y turnos de reserva
 * (`TURNOS_RESERVA` en src/lib/reservas.js). El panel los edita; nada más en
 * la aplicación los asume como constantes.
 */
class HorariosTurnosSeeder extends Seeder
{
    /**
     * @var list<array{nombre: string, dias: list<int>, abre: string, cierra: string}>
     */
    public const HORARIOS = [
        ['nombre' => 'Desayunos', 'dias' => [2, 3, 4, 5, 6, 0], 'abre' => '08:00', 'cierra' => '12:00'],
        ['nombre' => 'Almuerzos', 'dias' => [1, 2, 3, 4, 5, 6, 0], 'abre' => '12:00', 'cierra' => '17:00'],
    ];

    /**
     * @var list<array{id: string, nombre: string, slots: list<string>}>
     */
    public const TURNOS = [
        ['id' => 'almuerzo', 'nombre' => 'Almuerzo', 'slots' => ['12:00', '12:30', '13:00', '13:30']],
        ['id' => 'cena', 'nombre' => 'Cena', 'slots' => ['19:00', '19:30', '20:00', '20:30', '21:00', '21:30']],
    ];

    public function run(): void
    {
        foreach (self::HORARIOS as $horario) {
            Horario::updateOrCreate(
                ['nombre' => $horario['nombre']],
                $horario + ['activa' => true],
            );
        }

        foreach (self::TURNOS as $turno) {
            Turno::updateOrCreate(
                ['id' => $turno['id']],
                $turno + ['activa' => true],
            );
        }
    }
}
