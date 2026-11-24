<?php

namespace Tests\Feature;

use App\Enums\EstadoReserva;
use App\Enums\Rol;
use App\Models\Categoria;
use App\Models\Horario;
use App\Models\Mesa;
use App\Models\Plato;
use App\Models\Promocion;
use App\Models\Reserva;
use App\Models\Turno;
use App\Models\User;
use App\Support\Fechas;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\HorariosTurnosSeeder;
use Database\Seeders\MesasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_seed_base_reproduce_la_carta_las_mesas_y_los_turnos(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Mesas: 12 del prototipo, 38 asientos activos.
        $this->assertCount(count(MesasSeeder::MESAS), Mesa::query()->get());
        $this->assertSame(38, (int) Mesa::query()->sum('capacidad'));
        $this->assertSame(12, Mesa::query()->activas()->count());

        // Carta: 8 categorías, 22 platos, uno agotado.
        $this->assertSame(8, Categoria::query()->count());
        $this->assertSame(22, Plato::query()->count());
        $this->assertSame(1, Plato::query()->where('disponible', false)->count());
        $this->assertTrue(
            Plato::query()->where('nombre', 'Chaufa de mariscos')->firstOrFail()->disponible === false,
        );

        // Promociones: cinco reales del prototipo, una destacada.
        $this->assertSame(5, Promocion::query()->count());
        $this->assertSame(1, Promocion::query()->where('destacada', true)->count());
        $this->assertSame(
            'martes-de-cebiche-2x1',
            Promocion::query()->where('destacada', true)->firstOrFail()->slug,
        );

        // Horarios y turnos con los valores exactos del prototipo.
        $this->assertSame(2, Horario::query()->count());
        $this->assertSame(2, Turno::query()->count());
        $this->assertSame(['12:00', '12:30', '13:00', '13:30'], Turno::query()->findOrFail('almuerzo')->slots);
        $this->assertSame(
            ['19:00', '19:30', '20:00', '20:30', '21:00', '21:30'],
            Turno::query()->findOrFail('cena')->slots,
        );
        $this->assertSame(
            HorariosTurnosSeeder::HORARIOS[1]['dias'],
            Horario::query()->where('nombre', 'Almuerzos')->firstOrFail()->dias,
        );

        // Cuentas demo con sus credenciales funcionales.
        $admin = User::query()->where('correo', 'ana.quispe@elhuarike.pe')->firstOrFail();
        $cliente = User::query()->where('correo', 'demo@elhuarike.com')->firstOrFail();

        $this->assertSame(Rol::Admin, $admin->rol);
        $this->assertSame(Rol::Cliente, $cliente->rol);
        $this->assertTrue(Hash::check('panel2026', $admin->password));
        $this->assertTrue(Hash::check('reservas2024', $cliente->password));
        $this->assertArrayNotHasKey('password', $admin->toArray());
    }

    public function test_el_seed_es_idempotente(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(12, Mesa::query()->count());
        $this->assertSame(8, Categoria::query()->count());
        $this->assertSame(22, Plato::query()->count());
        $this->assertSame(5, Promocion::query()->count());
        $this->assertSame(2, Horario::query()->count());
        $this->assertSame(2, Turno::query()->count());
        $this->assertSame(2, User::query()->count());
        $this->assertSame(count(CatalogoSeeder::CARTA), Categoria::query()->count());
    }

    public function test_el_seed_demo_crea_doce_reservas_relativas_a_hoy(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);

        $reservas = Reserva::query()->with('mesa')->get();

        $this->assertCount(12, $reservas);
        $this->assertSame(
            array_map(fn (int $i) => 'HRK-'.(9001 + $i), range(0, 11)),
            $reservas->sortBy('folio')->pluck('folio')->values()->all(),
        );

        // Reparto del prototipo: 9 de hoy, 2 de ayer y 1 de mañana.
        $hoy = Fechas::hoy()->toDateString();
        $this->assertSame(9, Reserva::query()->whereDate('fecha', $hoy)->count());
        $this->assertSame(2, Reserva::query()->whereDate('fecha', Fechas::hoy()->subDay()->toDateString())->count());
        $this->assertSame(1, Reserva::query()->whereDate('fecha', Fechas::hoy()->addDay()->toDateString())->count());
        $this->assertSame(1, $reservas->filter(fn (Reserva $r) => $r->estado === EstadoReserva::NoShow)->count());
        $this->assertSame(1, $reservas->filter(fn (Reserva $r) => $r->estado === EstadoReserva::Finalizada)->count());
        $this->assertSame(1, $reservas->filter(fn (Reserva $r) => $r->estado === EstadoReserva::Cancelada)->count());
        $this->assertSame(7, $reservas->filter(fn (Reserva $r) => $r->estado === EstadoReserva::Confirmada)->count());
        $this->assertSame(2, $reservas->filter(fn (Reserva $r) => $r->estado === EstadoReserva::PorConfirmar)->count());

        // Todas creadas por el panel y con mesa asignada.
        $this->assertSame([DemoSeeder::AUTOR], $reservas->pluck('creada_por')->unique()->values()->all());
        $this->assertNull($reservas->firstWhere('mesa_id', null));

        // La reserva confirmada lleva su entrada de historial.
        $confirmada = $reservas->firstWhere('folio', 'HRK-9001');
        $this->assertSame(
            'Confirmada por WhatsApp al cliente',
            $confirmada->historial()->firstOrFail()->texto,
        );
    }

    public function test_el_endpoint_de_salud_responde(): void
    {
        $this->get('/up')->assertStatus(200);
    }
}
