<?php

namespace Tests\Feature;

use App\Models\Horario;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\Turno;
use App\Models\User;
use App\Support\Fechas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PanelHorariosTurnosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $manana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'America/Lima'));

        $this->admin = User::query()->where('correo', 'ana.quispe@elhuarike.pe')->firstOrFail();
        $this->manana = Fechas::hoy()->addDay()->toDateString();
    }

    private function reservaFutura(string $hora): Reserva
    {
        return Reserva::create([
            'folio' => 'HRK-'.random_int(5000, 8999),
            'nombre' => 'Cliente futuro',
            'correo' => 'futuro@correo.pe',
            'telefono' => '987654321',
            'fecha' => $this->manana,
            'hora' => $hora,
            'personas' => 2,
            'mesa_id' => Mesa::query()->where('numero', 1)->value('id'),
            'estado' => 'confirmada',
            'subtotal' => 0,
        ]);
    }

    public function test_el_panel_lista_todos_los_horarios_y_turnos_incluidos_los_inactivos(): void
    {
        Horario::query()->where('nombre', 'Desayunos')->firstOrFail()->forceFill(['activa' => false])->save();
        Turno::query()->findOrFail('cena')->forceFill(['activa' => false])->save();

        $this->actuarComo($this->admin)->getJson('/api/v1/panel/horarios')
            ->assertOk()
            ->assertJsonCount(2, 'horarios')
            ->assertJsonPath('horarios.0.activa', false);

        $this->actuarComo($this->admin)->getJson('/api/v1/panel/turnos')
            ->assertOk()
            ->assertJsonCount(2, 'turnos');
    }

    public function test_crea_una_franja_validando_dias_y_horas(): void
    {
        $this->actuarComo($this->admin)->postJson('/api/v1/panel/horarios', [
            'nombre' => 'Cenas',
            'dias' => [5, 6],
            'abre' => '18:00',
            'cierra' => '23:00',
        ])->assertCreated()
            ->assertJsonPath('data.nombre', 'Cenas')
            ->assertJsonPath('data.dias', [5, 6])
            ->assertJsonPath('data.abre', '18:00')
            ->assertJsonPath('data.activa', true);
    }

    public function test_valida_horario_sin_dias_y_con_cierre_anterior_a_la_apertura(): void
    {
        $this->actuarComo($this->admin)->postJson('/api/v1/panel/horarios', [
            'nombre' => 'Madrugada',
            'dias' => [],
            'abre' => '18:00',
            'cierra' => '09:00',
        ])->assertStatus(422)
            ->assertJsonPath('errors.dias.0', 'Elige al menos un día de atención.')
            ->assertJsonPath('errors.cierra.0', 'La hora de cierre debe ser posterior a la de apertura.');
    }

    public function test_crea_un_turno_con_slots_unicos_y_ordenados(): void
    {
        $this->actuarComo($this->admin)->postJson('/api/v1/panel/turnos', [
            'id' => 'merienda',
            'nombre' => 'Merienda',
            'slots' => ['17:00', '16:00', '17:00', '16:30'],
        ])->assertCreated()
            ->assertJsonPath('data.id', 'merienda')
            ->assertJsonPath('data.slots', ['16:00', '16:30', '17:00']);
    }

    public function test_valida_formato_de_slots_e_identificador_repetido(): void
    {
        $this->actuarComo($this->admin)->postJson('/api/v1/panel/turnos', [
            'id' => 'ALMUERZO',
            'nombre' => 'Otro almuerzo',
            'slots' => ['25:00'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'id' => 'Ya existe un turno con ese identificador.',
                'slots.0' => 'Cada horario debe tener el formato HH:MM.',
            ]);
    }

    public function test_borrar_un_turno_con_reservas_futuras_se_rechaza(): void
    {
        $this->reservaFutura('20:00');

        $this->actuarComo($this->admin)->deleteJson('/api/v1/panel/turnos/cena')
            ->assertStatus(409)
            ->assertJsonPath('motivo', 'turno-con-reservas');

        $this->assertDatabaseHas('turnos', ['id' => 'cena']);
    }

    public function test_borra_un_turno_sin_reservas_futuras(): void
    {
        $this->actuarComo($this->admin)->deleteJson('/api/v1/panel/turnos/cena')->assertOk();

        $this->assertDatabaseMissing('turnos', ['id' => 'cena']);
    }

    public function test_borrar_una_franja_con_reservas_futuras_se_rechaza(): void
    {
        $this->reservaFutura('20:00');

        $horario = Horario::create([
            'nombre' => 'Cenas',
            'dias' => [0, 1, 2, 3, 4, 5, 6],
            'abre' => '19:00',
            'cierra' => '22:00',
            'activa' => true,
        ]);

        $this->actuarComo($this->admin)->deleteJson("/api/v1/panel/horarios/{$horario->id}")
            ->assertStatus(409)
            ->assertJsonPath('motivo', 'franja-con-reservas');
    }

    public function test_desactivar_un_turno_lo_saca_de_la_disponibilidad_sin_reseed(): void
    {
        $antes = $this->getJson('/api/v1/disponibilidad?fecha='.$this->manana.'&personas=4')->assertOk();
        $antes->assertJsonCount(10, 'slots');

        $this->actuarComo($this->admin)->patchJson('/api/v1/panel/turnos/cena', [
            'nombre' => 'Cena',
            'slots' => ['19:00', '19:30', '20:00', '20:30', '21:00', '21:30'],
            'activa' => false,
        ])->assertOk()->assertJsonPath('data.activa', false);

        $despues = $this->getJson('/api/v1/disponibilidad?fecha='.$this->manana.'&personas=4')->assertOk();

        $despues->assertJsonCount(4, 'slots');
        $this->assertSame(['almuerzo'], collect($despues->json('slots'))->pluck('turno')->unique()->values()->all());
    }

    public function test_editar_los_slots_del_turno_cambia_la_validacion_de_hora(): void
    {
        $cliente = User::query()->where('correo', 'demo@elhuarike.com')->firstOrFail();

        // 18:00 no existe todavía.
        $this->actuarComo($cliente)->postJson('/api/v1/reservas', [
            'fecha' => $this->manana,
            'hora' => '18:00',
            'personas' => 2,
        ])->assertStatus(422)
            ->assertJsonPath('errors.hora.0', 'Elige un horario de reserva disponible.');

        $this->actuarComo($this->admin)->patchJson('/api/v1/panel/turnos/cena', [
            'nombre' => 'Cena',
            'slots' => ['18:00', '19:00', '20:00'],
        ])->assertOk();

        $this->actuarComo($cliente)->postJson('/api/v1/reservas', [
            'fecha' => $this->manana,
            'hora' => '18:00',
            'personas' => 2,
        ])->assertCreated();
    }

    public function test_editar_y_activar_una_franja(): void
    {
        $horario = Horario::query()->where('nombre', 'Desayunos')->firstOrFail();

        $this->actuarComo($this->admin)->patchJson("/api/v1/panel/horarios/{$horario->id}", [
            'nombre' => 'Desayunos y brunch',
            'dias' => [0, 6],
            'abre' => '09:00',
            'cierra' => '13:00',
            'activa' => false,
        ])->assertOk()
            ->assertJsonPath('data.nombre', 'Desayunos y brunch')
            ->assertJsonPath('data.dias', [0, 6])
            ->assertJsonPath('data.activa', false);
    }

    public function test_un_cliente_no_edita_horarios_ni_turnos(): void
    {
        $cliente = User::query()->where('correo', 'demo@elhuarike.com')->firstOrFail();

        $this->actuarComo($cliente)->getJson('/api/v1/panel/horarios')->assertStatus(403);
        $this->actuarComo($cliente)->getJson('/api/v1/panel/turnos')->assertStatus(403);
        $this->actuarComo($cliente)->deleteJson('/api/v1/panel/turnos/cena')->assertStatus(403);
    }
}
