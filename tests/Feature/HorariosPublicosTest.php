<?php

namespace Tests\Feature;

use App\Models\Horario;
use App\Models\Turno;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HorariosPublicosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_los_turnos_publicos_traen_los_slots_vigentes(): void
    {
        $respuesta = $this->getJson('/api/v1/turnos')->assertOk();

        $respuesta->assertJsonCount(2, 'turnos')
            ->assertJsonPath('turnos.0.id', 'almuerzo')
            ->assertJsonPath('turnos.0.slots', ['12:00', '12:30', '13:00', '13:30'])
            ->assertJsonPath('turnos.1.slots', ['19:00', '19:30', '20:00', '20:30', '21:00', '21:30']);
    }

    public function test_el_estado_del_local_indica_abierto_y_la_hora_de_cierre(): void
    {
        // 13:00 cae dentro de la franja de almuerzos (12:00-17:00).
        Carbon::setTestNow(Carbon::parse('2026-11-25 13:00:00', 'America/Lima'));

        $this->getJson('/api/v1/horarios')
            ->assertOk()
            ->assertJsonPath('estado.abierto', true)
            ->assertJsonPath('estado.franja', 'almuerzos')
            ->assertJsonPath('estado.cierra_a', '17:00')
            ->assertJsonPath('estado.mensaje', 'Abierto ahora · cierra a las 17:00')
            ->assertJsonPath('estado.proxima_apertura', null)
            ->assertJsonCount(2, 'horarios');
    }

    public function test_fuera_de_franja_informa_la_proxima_apertura(): void
    {
        // 03:00: cerrado; la próxima apertura es el desayuno de ese mismo día.
        Carbon::setTestNow(Carbon::parse('2026-11-25 03:00:00', 'America/Lima'));

        $respuesta = $this->getJson('/api/v1/horarios')->assertOk();

        $respuesta->assertJsonPath('estado.abierto', false)
            ->assertJsonPath('estado.franja', null)
            ->assertJsonPath('estado.cierra_a', null)
            ->assertJsonPath('estado.proxima_apertura.hora', '08:00')
            ->assertJsonPath('estado.proxima_apertura.franja', 'desayunos');

        $this->assertStringStartsWith('Cerrado · abre el ', $respuesta->json('estado.mensaje'));
        $this->assertStringEndsWith('a las 8:00', $respuesta->json('estado.mensaje'));
    }

    public function test_una_franja_inactiva_no_cuenta_para_el_estado(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 13:00:00', 'America/Lima'));

        Horario::query()->where('nombre', 'Almuerzos')->firstOrFail()
            ->forceFill(['activa' => false])->save();

        $respuesta = $this->getJson('/api/v1/horarios')->assertOk();

        $respuesta->assertJsonPath('estado.abierto', false);
        $this->assertStringContainsString('Cerrado', $respuesta->json('estado.mensaje'));
        $this->assertCount(1, $respuesta->json('horarios'));
    }

    public function test_un_turno_inactivo_no_se_ofrece_al_reservar(): void
    {
        Turno::query()->findOrFail('cena')->forceFill(['activa' => false])->save();

        $this->getJson('/api/v1/turnos')
            ->assertOk()
            ->assertJsonCount(1, 'turnos')
            ->assertJsonPath('turnos.0.id', 'almuerzo');
    }
}
