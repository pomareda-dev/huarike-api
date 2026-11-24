<?php

namespace Tests\Feature;

use App\Models\Mesa;
use App\Models\Reserva;
use App\Models\User;
use App\Support\Fechas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-11-25 22:00:00', 'America/Lima'));

        $this->token = User::query()
            ->where('correo', 'ana.quispe@elhuarike.pe')
            ->firstOrFail()
            ->createToken('spa')
            ->plainTextToken;
    }

    private function reserva(string $fecha, string $hora, int $personas, string $estado, int $numeroMesa = 1): void
    {
        static $n = 6000;

        $mesa = Mesa::query()->where('numero', $numeroMesa)->firstOrFail();

        Reserva::create([
            'folio' => 'HRK-'.($n++),
            'nombre' => 'Cliente '.$n,
            'correo' => 'cliente'.$n.'@correo.pe',
            'telefono' => '987654321',
            'fecha' => $fecha,
            'hora' => $hora,
            'personas' => $personas,
            'mesa_id' => $estado === 'lista-espera' ? null : $mesa->id,
            'estado' => $estado,
            'subtotal' => 0,
        ]);
    }

    public function test_las_metricas_del_dia_siguen_las_reglas_del_prototipo(): void
    {
        $hoy = Fechas::hoy()->toDateString();
        $ayer = Fechas::hoy()->subDay()->toDateString();

        // Hoy: 2 confirmadas (2 + 4 personas), 1 cancelada, 1 no-show, 1 en espera.
        $this->reserva($hoy, '13:00', 2, 'confirmada', 1);
        $this->reserva($hoy, '20:00', 4, 'confirmada', 2);
        $this->reserva($hoy, '20:30', 4, 'cancelada', 3);
        $this->reserva($hoy, '21:00', 4, 'no-show', 4);
        $this->reserva($hoy, '21:30', 6, 'lista-espera');

        // Ayer: 2 confirmadas para probar el delta.
        $this->reserva($ayer, '13:00', 2, 'confirmada', 5);
        $this->reserva($ayer, '20:00', 2, 'confirmada', 6);

        $respuesta = $this->withToken($this->token)->getJson("/api/v1/panel/reportes?fecha={$hoy}")->assertOk();

        // Cuenta todo menos lista-espera: 2 confirmadas + cancelada + no-show.
        $respuesta->assertJsonPath('reservas', 4)
            ->assertJsonPath('comensales', 6)
            ->assertJsonPath('no_show', 2)
            ->assertJsonPath('porcentaje_no_show', 50)
            ->assertJsonPath('reservas_ayer', 2)
            ->assertJsonPath('delta', 2)
            ->assertJsonPath('mesas_activas', 12)
            ->assertJsonStructure(['ocupacion' => ['almuerzo', 'cena', 'promedio']]);
    }

    public function test_sin_reservas_las_metricas_son_cero(): void
    {
        $hoy = Fechas::hoy()->toDateString();

        $this->withToken($this->token)->getJson("/api/v1/panel/reportes?fecha={$hoy}")
            ->assertOk()
            ->assertJsonPath('reservas', 0)
            ->assertJsonPath('comensales', 0)
            ->assertJsonPath('no_show', 0)
            ->assertJsonPath('porcentaje_no_show', 0)
            ->assertJsonPath('delta', 0)
            ->assertJsonPath('ocupacion.almuerzo', 0)
            ->assertJsonPath('ocupacion.promedio', 0);
    }

    public function test_la_ocupacion_por_turno_cuenta_mesas_sobre_slots_y_mesas_activas(): void
    {
        $hoy = Fechas::hoy()->toDateString();

        // Almuerzo: 2 mesas ocupadas en 4 slots × 12 mesas = 48 capacidades.
        $this->reserva($hoy, '12:00', 2, 'confirmada', 1);
        $this->reserva($hoy, '13:00', 2, 'confirmada', 2);

        $respuesta = $this->withToken($this->token)->getJson("/api/v1/panel/reportes?fecha={$hoy}")->assertOk();

        // 2 / 48 = 4.17% → 4.
        $respuesta->assertJsonPath('ocupacion.almuerzo', 4)
            ->assertJsonPath('ocupacion.cena', 0);
    }

    public function test_el_rango_devuelve_una_fila_por_dia(): void
    {
        $hoy = Fechas::hoy()->toDateString();
        $ayer = Fechas::hoy()->subDay()->toDateString();

        $this->reserva($ayer, '20:00', 4, 'confirmada', 2);
        $this->reserva($hoy, '20:00', 2, 'confirmada', 1);

        $respuesta = $this->withToken($this->token)
            ->getJson("/api/v1/panel/reportes/rango?desde={$ayer}&hasta={$hoy}")
            ->assertOk();

        $respuesta->assertJsonCount(2, 'dias')
            ->assertJsonPath('dias.0.fecha', $ayer)
            ->assertJsonPath('dias.0.reservas', 1)
            ->assertJsonPath('dias.0.comensales', 4)
            ->assertJsonPath('dias.1.fecha', $hoy)
            ->assertJsonPath('dias.1.reservas', 1);
    }

    public function test_el_rango_valida_el_orden_y_el_maximo_de_dias(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/panel/reportes/rango?desde=2026-11-25&hasta=2026-11-24')
            ->assertStatus(422)
            ->assertJsonPath('errors.hasta.0', 'La fecha de fin debe ser posterior a la de inicio.');

        $this->withToken($this->token)
            ->getJson('/api/v1/panel/reportes/rango?desde=2024-01-01&hasta=2026-11-25')
            ->assertStatus(422)
            ->assertJsonPath('errors.hasta.0', 'El rango no puede superar 366 días.');
    }
}
