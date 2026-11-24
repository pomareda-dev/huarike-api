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

class CalendarioTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'America/Lima'));

        $this->token = User::query()
            ->where('correo', 'ana.quispe@elhuarike.pe')
            ->firstOrFail()
            ->createToken('spa')
            ->plainTextToken;
    }

    public function test_el_mes_devuelve_siempre_42_celdas_de_lunes_a_domingo(): void
    {
        $respuesta = $this->withToken($this->token)
            ->getJson('/api/v1/panel/calendario?anio=2026&mes=11')
            ->assertOk();

        $respuesta->assertJsonPath('anio', 2026)
            ->assertJsonPath('mes', 11)
            ->assertJsonCount(42, 'celdas')
            ->assertJsonStructure(['celdas' => [[
                'fecha', 'dia', 'del_mes', 'pasado', 'es_hoy',
                'reservas', 'personas', 'ocupacion' => ['almuerzo', 'cena', 'promedio'],
                'ocupadas_pico', 'mesas_activas',
            ]]]);

        $celdas = $respuesta->json('celdas');

        // La primera celda es lunes y la última domingo.
        $this->assertSame(1, Fechas::diaSemana($celdas[0]['fecha']));
        $this->assertSame(0, Fechas::diaSemana($celdas[41]['fecha']));

        // Noviembre de 2026 empieza domingo: la primera celda es del mes anterior.
        $this->assertFalse($celdas[0]['del_mes']);
        $this->assertSame(12, $celdas[0]['mesas_activas']);
    }

    public function test_las_celdas_marcan_hoy_pasado_y_los_totales_del_dia(): void
    {
        $hoy = Fechas::hoy()->toDateString();
        $mesa = Mesa::query()->where('numero', 4)->firstOrFail();

        Reserva::create([
            'folio' => 'HRK-7001',
            'nombre' => 'Grupo del día',
            'correo' => 'grupo@correo.pe',
            'telefono' => '987654321',
            'fecha' => $hoy,
            'hora' => '20:00',
            'personas' => 4,
            'mesa_id' => $mesa->id,
            'estado' => 'confirmada',
            'subtotal' => 0,
        ]);

        $celdas = collect($this->withToken($this->token)
            ->getJson('/api/v1/panel/calendario?anio=2026&mes=11')
            ->json('celdas'));

        $deHoy = $celdas->firstWhere('fecha', $hoy);
        $ayer = $celdas->firstWhere('fecha', Fechas::hoy()->subDay()->toDateString());

        $this->assertTrue($deHoy['es_hoy']);
        $this->assertFalse($deHoy['pasado']);
        $this->assertSame(1, $deHoy['reservas']);
        $this->assertSame(4, $deHoy['personas']);
        $this->assertSame(1, $deHoy['ocupadas_pico']);
        $this->assertTrue($ayer['pasado']);
    }

    public function test_la_semana_devuelve_siete_dias_desde_el_lunes(): void
    {
        $respuesta = $this->withToken($this->token)
            ->getJson('/api/v1/panel/calendario/semana?fecha=2026-11-25')
            ->assertOk();

        $respuesta->assertJsonCount(7, 'celdas')
            ->assertJsonPath('desde', '2026-11-23')
            ->assertJsonPath('hasta', '2026-11-29');

        $fechas = collect($respuesta->json('celdas'))->pluck('fecha')->all();

        $this->assertSame('2026-11-23', $fechas[0]);
        $this->assertSame('2026-11-29', $fechas[6]);
    }

    public function test_valida_anio_mes_y_fecha_de_semana(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/panel/calendario?anio=2026&mes=13')
            ->assertStatus(422)
            ->assertJsonPath('errors.mes.0', 'El mes debe estar entre 1 y 12.');

        $this->withToken($this->token)
            ->getJson('/api/v1/panel/calendario/semana?fecha=25-11-2026')
            ->assertStatus(422)
            ->assertJsonPath('errors.fecha.0', 'Elige una fecha válida.');
    }
}
