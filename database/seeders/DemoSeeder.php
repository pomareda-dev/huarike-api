<?php

namespace Database\Seeders;

use App\Enums\EstadoReserva;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\ReservaHistorial;
use App\Support\Fechas;
use Illuminate\Database\Seeder;

/**
 * Reservas de demostración del panel (`reservasDemoPanel` en
 * src/lib/panelDemo.js): doce reservas relativas al día de hoy, con folios
 * HRK-9001… Solo para entornos locales.
 */
class DemoSeeder extends Seeder
{
    /**
     * @var list<array{nombre: string, correo: string, telefono: string, notas: string|null}>
     */
    public const PERSONAS = [
        ['nombre' => 'Lucía Ramírez', 'correo' => 'demo@elhuarike.com', 'telefono' => '+51 987 654 321', 'notas' => 'Cumpleaños de la clienta: traer postre y una vela.'],
        ['nombre' => 'Jorge Mendoza', 'correo' => 'jorge.mendoza@correo.pe', 'telefono' => '+51 986 112 233', 'notas' => null],
        ['nombre' => 'Rosa Ttito', 'correo' => 'rosa.ttito@correo.pe', 'telefono' => '+51 955 334 112', 'notas' => 'Mesa junto a la ventana si es posible.'],
        ['nombre' => 'Carlos Ayala', 'correo' => 'carlos.ayala@correo.pe', 'telefono' => '+51 944 221 556', 'notas' => null],
        ['nombre' => 'Milagros Sosa', 'correo' => 'milagros.sosa@correo.pe', 'telefono' => '+51 933 778 990', 'notas' => 'Grupo familiar con dos niños.'],
        ['nombre' => 'Diego Huamán', 'correo' => 'diego.huaman@correo.pe', 'telefono' => '+51 922 445 667', 'notas' => null],
        ['nombre' => 'Ana Torres', 'correo' => 'ana.torres@correo.pe', 'telefono' => '+51 911 556 778', 'notas' => null],
        ['nombre' => 'Rocío Vega', 'correo' => 'rocio.vega@correo.pe', 'telefono' => '+51 900 667 889', 'notas' => 'Alergia al maní.'],
        ['nombre' => 'Marco Chávez', 'correo' => 'marco.chavez@correo.pe', 'telefono' => '+51 988 123 456', 'notas' => null],
        ['nombre' => 'Diego Paredes', 'correo' => 'diego.paredes@correo.pe', 'telefono' => '+51 977 234 567', 'notas' => null],
        ['nombre' => 'Sofía Vargas', 'correo' => 'sofia.vargas@correo.com', 'telefono' => '+51 966 345 678', 'notas' => null],
        ['nombre' => 'Jorge Salas', 'correo' => 'jorge.salas@correo.pe', 'telefono' => '+51 955 456 789', 'notas' => null],
    ];

    public const AUTOR = 'Ana Quispe';

    public function run(): void
    {
        $hoy = Fechas::hoy();
        $ayer = $hoy->subDay()->toDateString();
        $manana = $hoy->addDay()->toDateString();
        $fechaHoy = $hoy->toDateString();

        $mesas = Mesa::query()->pluck('id', 'numero');

        $demo = [
            [0, $fechaHoy, '12:30', 2, 1, EstadoReserva::Confirmada],
            [1, $fechaHoy, '13:00', 4, 6, EstadoReserva::Confirmada],
            [2, $fechaHoy, '13:30', 6, 3, EstadoReserva::PorConfirmar],
            [3, $fechaHoy, '19:00', 4, 4, EstadoReserva::Confirmada],
            [4, $fechaHoy, '19:30', 2, 7, EstadoReserva::Confirmada],
            [5, $fechaHoy, '20:00', 4, 5, EstadoReserva::Confirmada],
            [6, $fechaHoy, '20:30', 2, 9, EstadoReserva::PorConfirmar],
            [7, $fechaHoy, '21:00', 6, 2, EstadoReserva::Confirmada],
            [8, $fechaHoy, '21:30', 2, 1, EstadoReserva::Cancelada],
            [9, $ayer, '20:00', 4, 4, EstadoReserva::NoShow],
            [10, $ayer, '21:00', 2, 8, EstadoReserva::Finalizada],
            [11, $manana, '20:00', 4, 6, EstadoReserva::Confirmada],
        ];

        foreach ($demo as $indice => [$persona, $fecha, $hora, $personas, $numeroMesa, $estado]) {
            $datos = self::PERSONAS[$persona];
            $folio = 'HRK-'.(9001 + $indice);
            $mesaId = $mesas[$numeroMesa] ?? null;

            $reserva = Reserva::updateOrCreate(
                ['folio' => $folio],
                [
                    'user_id' => null,
                    'nombre' => $datos['nombre'],
                    'correo' => $datos['correo'],
                    'telefono' => $datos['telefono'],
                    'notas' => $datos['notas'],
                    'fecha' => $fecha,
                    'hora' => $hora,
                    'personas' => $personas,
                    'mesa_id' => $mesaId,
                    'estado' => $estado,
                    'subtotal' => 0,
                    'creada_por' => self::AUTOR,
                ],
            );

            $reserva->historial()->delete();

            if ($estado === EstadoReserva::Confirmada) {
                ReservaHistorial::create([
                    'reserva_id' => $reserva->id,
                    'tipo' => 'estado',
                    'texto' => 'Confirmada por WhatsApp al cliente',
                    'autor' => self::AUTOR,
                ]);
            }
        }
    }
}
