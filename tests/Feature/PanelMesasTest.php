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

class PanelMesasTest extends TestCase
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

    public function test_el_panel_lista_todas_las_mesas_y_los_asientos_activos(): void
    {
        Mesa::query()->where('numero', 12)->firstOrFail()->forceFill(['activa' => false])->save();

        $this->withToken($this->token)->getJson('/api/v1/panel/mesas')
            ->assertOk()
            ->assertJsonCount(12, 'mesas')
            ->assertJsonPath('asientos_activos', 36);
    }

    public function test_crea_una_mesa_con_el_numero_siguiente(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/panel/mesas', [
            'zona' => 'Terraza',
            'capacidad' => 4,
        ])->assertCreated()
            ->assertJsonPath('data.numero', 13)
            ->assertJsonPath('data.zona', 'Terraza')
            ->assertJsonPath('data.activa', true);
    }

    public function test_valida_zona_y_capacidad(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/panel/mesas', [
            'zona' => 'Barra',
            'capacidad' => 12,
        ])->assertStatus(422)
            ->assertJsonPath('errors.zona.0', 'Elige una zona del salón')
            ->assertJsonPath('errors.capacidad.0', 'La capacidad debe ser un número entre 1 y 8');
    }

    public function test_edita_zona_y_capacidad(): void
    {
        $mesa = Mesa::query()->where('numero', 2)->firstOrFail();

        $this->withToken($this->token)->patchJson("/api/v1/panel/mesas/{$mesa->id}", [
            'zona' => 'Salón',
            'capacidad' => 8,
        ])->assertOk()
            ->assertJsonPath('data.zona', 'Salón')
            ->assertJsonPath('data.capacidad', 8);
    }

    public function test_desactivar_una_mesa_la_saca_del_sitio_publico(): void
    {
        $mesa = Mesa::query()->where('numero', 8)->firstOrFail();

        $this->withToken($this->token)->patchJson("/api/v1/panel/mesas/{$mesa->id}/desactivar")
            ->assertOk()
            ->assertJsonPath('data.activa', false);

        $publicas = collect($this->getJson('/api/v1/mesas')->json('mesas'))->pluck('numero')->all();
        $this->assertNotContains(8, $publicas);

        $this->withToken($this->token)->patchJson("/api/v1/panel/mesas/{$mesa->id}/activar")
            ->assertOk()
            ->assertJsonPath('data.activa', true);
    }

    public function test_no_elimina_una_mesa_con_reservas_futuras(): void
    {
        $mesa = Mesa::query()->where('numero', 4)->firstOrFail();

        Reserva::create([
            'folio' => 'HRK-7001',
            'nombre' => 'Cliente futuro',
            'correo' => 'futuro@correo.pe',
            'telefono' => '987654321',
            'fecha' => Fechas::hoy()->addDay()->toDateString(),
            'hora' => '20:00',
            'personas' => 2,
            'mesa_id' => $mesa->id,
            'estado' => 'confirmada',
            'subtotal' => 0,
        ]);

        $this->withToken($this->token)->deleteJson("/api/v1/panel/mesas/{$mesa->id}")
            ->assertStatus(409)
            ->assertJsonPath('motivo', 'mesa-con-reservas');

        $this->assertNotSoftDeleted('mesas', ['id' => $mesa->id]);
    }

    public function test_elimina_con_soft_delete_una_mesa_sin_reservas_futuras(): void
    {
        $mesa = Mesa::query()->where('numero', 11)->firstOrFail();

        $this->withToken($this->token)->deleteJson("/api/v1/panel/mesas/{$mesa->id}")->assertOk();

        $this->assertSoftDeleted('mesas', ['id' => $mesa->id]);
    }

    public function test_un_cliente_no_administra_mesas(): void
    {
        $cliente = User::query()->where('correo', 'demo@elhuarike.com')->firstOrFail();
        $token = $cliente->createToken('spa')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/panel/mesas')->assertStatus(403);
        $this->withToken($token)->postJson('/api/v1/panel/mesas', ['zona' => 'Salón', 'capacidad' => 2])
            ->assertStatus(403);
    }
}
