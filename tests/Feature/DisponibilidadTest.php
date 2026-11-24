<?php

namespace Tests\Feature;

use App\Models\Mesa;
use App\Models\Reserva;
use App\Support\Fechas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DisponibilidadTest extends TestCase
{
    use RefreshDatabase;

    private string $hoy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'America/Lima'));
        $this->hoy = Fechas::hoy()->toDateString();
    }

    private function reservarDirecto(int $mesaNumero, string $hora, string $fecha): Reserva
    {
        $mesa = Mesa::query()->where('numero', $mesaNumero)->firstOrFail();

        return Reserva::create([
            'folio' => 'HRK-'.random_int(5000, 8000),
            'nombre' => 'Cliente de prueba',
            'correo' => 'prueba@correo.pe',
            'telefono' => '987654321',
            'fecha' => $fecha,
            'hora' => $hora,
            'personas' => 2,
            'mesa_id' => $mesa->id,
            'estado' => 'confirmada',
            'subtotal' => 0,
        ]);
    }

    public function test_la_disponibilidad_lista_los_slots_de_los_turnos_vigentes(): void
    {
        $respuesta = $this->getJson("/api/v1/disponibilidad?fecha={$this->hoy}&personas=4")->assertOk();

        $respuesta->assertJsonPath('fecha', $this->hoy)
            ->assertJsonPath('personas', 4)
            ->assertJsonPath('mesas_activas', 12)
            ->assertJsonCount(10, 'slots');

        // Cinco mesas activas tienen capacidad para 4 personas.
        $respuesta->assertJsonPath('slots.0.hora', '12:00')
            ->assertJsonPath('slots.0.turno', 'almuerzo')
            ->assertJsonPath('slots.0.estado', 'disponible')
            ->assertJsonPath('slots.0.mesas_libres', 5)
            ->assertJsonPath('slots.0.resumen', 'Quedan 5 mesas')
            ->assertJsonPath('slots.9.turno', 'cena');
    }

    public function test_un_grupo_grande_ve_alta_demanda(): void
    {
        $respuesta = $this->getJson("/api/v1/disponibilidad?fecha={$this->hoy}&personas=6")->assertOk();

        // Solo las mesas 3 y 6 tienen 6 asientos.
        $respuesta->assertJsonPath('slots.0.mesas_libres', 2)
            ->assertJsonPath('slots.0.estado', 'alta-demanda')
            ->assertJsonPath('slots.0.resumen', 'Quedan 2 mesas');
    }

    public function test_una_reserva_real_ocupa_la_franja_y_las_siguientes_dentro_de_90_minutos(): void
    {
        $this->reservarDirecto(3, '13:00', $this->hoy);

        $slots = collect($this->getJson("/api/v1/disponibilidad?fecha={$this->hoy}&personas=6")->json('slots'))
            ->keyBy('hora');

        // Mesa 3 ocupada a las 13:00: queda solo la 6.
        $this->assertSame(1, $slots['13:00']['mesas_libres']);
        // 13:30 está a 30 minutos: sigue bloqueada.
        $this->assertSame(1, $slots['13:30']['mesas_libres']);
        // La cena está a 6 horas: no hay choque.
        $this->assertSame(2, $slots['19:00']['mesas_libres']);
    }

    public function test_una_reserva_cancelada_o_en_lista_de_espera_no_ocupa_la_mesa(): void
    {
        $cancelada = $this->reservarDirecto(3, '13:00', $this->hoy);
        $cancelada->forceFill(['estado' => 'cancelada'])->save();

        $espera = $this->reservarDirecto(3, '13:00', $this->hoy);
        $espera->forceFill(['estado' => 'lista-espera', 'mesa_id' => null])->save();

        $slots = collect($this->getJson("/api/v1/disponibilidad?fecha={$this->hoy}&personas=6")->json('slots'))
            ->keyBy('hora');

        $this->assertSame(2, $slots['13:00']['mesas_libres']);
    }

    public function test_una_mesa_inactiva_no_se_ofrece(): void
    {
        Mesa::query()->where('numero', 3)->firstOrFail()->forceFill(['activa' => false])->save();

        $slots = collect($this->getJson("/api/v1/disponibilidad?fecha={$this->hoy}&personas=6")->json('slots'))
            ->keyBy('hora');

        // Con la mesa 3 inactiva queda una sola mesa para 6 personas.
        $this->assertSame(1, $slots['13:00']['mesas_libres']);
        $this->assertSame('alta-demanda', $slots['13:00']['estado']);

        // Y las mesas para ese slot excluyen la inactiva.
        $mesas = collect($this->getJson("/api/v1/disponibilidad/mesas?fecha={$this->hoy}&hora=13:00&personas=6")->json('mesas'))
            ->pluck('numero')
            ->all();

        $this->assertSame([6], $mesas);
    }

    public function test_rechaza_una_fecha_pasada_y_un_grupo_fuera_de_rango(): void
    {
        $ayer = Fechas::hoy()->subDay()->toDateString();

        $this->getJson("/api/v1/disponibilidad?fecha={$ayer}&personas=4")
            ->assertStatus(422)
            ->assertJsonPath('errors.fecha.0', 'Elige una fecha de hoy en adelante.');

        $this->getJson("/api/v1/disponibilidad?fecha={$this->hoy}&personas=9")
            ->assertStatus(422)
            ->assertJsonPath('errors.personas.0', 'La reserva es de 1 a 6 personas.');
    }

    public function test_las_mesas_para_un_slot_excluyen_capacidad_insuficiente_y_choques(): void
    {
        $this->reservarDirecto(3, '13:00', $this->hoy);

        $respuesta = $this->getJson("/api/v1/disponibilidad/mesas?fecha={$this->hoy}&hora=13:00&personas=6")->assertOk();

        $respuesta->assertJsonCount(1, 'mesas')
            ->assertJsonPath('mesas.0.numero', 6)
            ->assertJsonPath('hora', '13:00');
    }

    public function test_la_lista_publica_de_mesas_solo_trae_activas(): void
    {
        Mesa::query()->where('numero', 12)->firstOrFail()->forceFill(['activa' => false])->save();

        $respuesta = $this->getJson('/api/v1/mesas')->assertOk();

        $respuesta->assertJsonCount(11, 'mesas');
        $this->assertNotContains(12, collect($respuesta->json('mesas'))->pluck('numero')->all());
        $respuesta->assertJsonStructure(['mesas' => [['id', 'numero', 'zona', 'capacidad']]]);
    }
}
