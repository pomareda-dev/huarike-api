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

class PanelCambiosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'America/Lima'));

        $this->admin = User::query()->where('correo', 'ana.quispe@elhuarike.pe')->firstOrFail();
    }

    public function test_sin_cambios_devuelve_la_lista_vacia(): void
    {
        $desde = Fechas::ahora()->addMinute()->toIso8601String();

        $this->actuarComo($this->admin)->getJson('/api/v1/panel/cambios?desde='.urlencode($desde))
            ->assertOk()
            ->assertJsonCount(0, 'cambios');
    }

    public function test_detecta_reservas_y_mesas_tocadas_despues_de_la_marca(): void
    {
        $marca = Fechas::ahora()->addSecond();

        Carbon::setTestNow(Carbon::parse('2026-11-25 10:05:00', 'America/Lima'));

        $reserva = Reserva::create([
            'folio' => 'HRK-9101',
            'nombre' => 'Cliente del polling',
            'correo' => 'polling@correo.pe',
            'telefono' => '987654321',
            'fecha' => Fechas::hoy()->toDateString(),
            'hora' => '20:00',
            'personas' => 2,
            'mesa_id' => Mesa::query()->where('numero', 4)->value('id'),
            'estado' => 'confirmada',
            'subtotal' => 0,
        ]);

        Mesa::query()->where('numero', 7)->firstOrFail()->forceFill(['capacidad' => 4])->save();

        $respuesta = $this->actuarComo($this->admin)
            ->getJson('/api/v1/panel/cambios?desde='.urlencode($marca->toIso8601String()))
            ->assertOk();

        $cambios = collect($respuesta->json('cambios'));

        $deReserva = $cambios->firstWhere('tipo', 'reserva');
        $deMesa = $cambios->firstWhere('tipo', 'mesa');

        $this->assertNotNull($deReserva);
        $this->assertSame('HRK-9101', $deReserva['folio']);
        $this->assertSame($reserva->id, $deReserva['id']);
        $this->assertArrayHasKey('actualizado_en', $deReserva);

        $this->assertNotNull($deMesa);
        $this->assertSame(7, $deMesa['numero']);
        $this->assertNull($deMesa['folio']);
    }

    public function test_exige_la_marca_de_tiempo(): void
    {
        $this->actuarComo($this->admin)->getJson('/api/v1/panel/cambios')
            ->assertStatus(422)
            ->assertJsonPath('errors.desde.0', 'Indica desde cuándo buscar cambios.');

        $this->actuarComo($this->admin)->getJson('/api/v1/panel/cambios?desde=ayer')
            ->assertStatus(422)
            ->assertJsonPath('errors.desde.0', 'La marca de tiempo de inicio no es válida.');
    }

    public function test_un_cliente_no_consulta_los_cambios_del_panel(): void
    {
        $cliente = User::query()->where('correo', 'demo@elhuarike.com')->firstOrFail();

        $this->actuarComo($cliente)
            ->getJson('/api/v1/panel/cambios?desde='.urlencode(Fechas::ahora()->toIso8601String()))
            ->assertStatus(403);
    }
}
