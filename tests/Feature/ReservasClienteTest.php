<?php

namespace Tests\Feature;

use App\Enums\EstadoReserva;
use App\Models\Mesa;
use App\Models\Plato;
use App\Models\Reserva;
use App\Models\User;
use App\Support\Fechas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReservasClienteTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private User $cliente;

    private string $manana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'America/Lima'));

        $this->cliente = User::query()->where('correo', 'demo@elhuarike.com')->firstOrFail();
        $this->token = $this->cliente->createToken('spa')->plainTextToken;
        $this->manana = Fechas::hoy()->addDay()->toDateString();
    }

    private function mesa(string $numero = '4'): Mesa
    {
        return Mesa::query()->where('numero', (int) $numero)->firstOrFail();
    }

    private function crearReserva(array $overrides = []): array
    {
        $cebiche = Plato::query()->where('nombre', 'Cebiche clásico')->firstOrFail();

        $cuerpo = array_merge([
            'fecha' => $this->manana,
            'hora' => '20:00',
            'personas' => 4,
            'mesa_id' => $this->mesa('4')->id,
            'notas' => 'Mesa junto a la ventana si es posible.',
            'platos' => [
                ['plato_id' => $cebiche->id, 'cantidad' => 2],
            ],
        ], $overrides);

        return $this->withToken($this->token)->postJson('/api/v1/reservas', $cuerpo)->json();
    }

    public function test_crea_una_reserva_confirmada_con_folio_preorden_e_historial(): void
    {
        $respuesta = $this->withToken($this->token)->postJson('/api/v1/reservas', [
            'fecha' => $this->manana,
            'hora' => '20:00',
            'personas' => 4,
            'mesa_id' => $this->mesa('4')->id,
            'notas' => 'Mesa junto a la ventana si es posible.',
            'platos' => [
                ['plato_id' => Plato::query()->where('nombre', 'Cebiche clásico')->value('id'), 'cantidad' => 2],
            ],
        ]);

        $respuesta->assertCreated()
            ->assertJsonPath('data.folio', 'HRK-2481')
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.turno', 'cena')
            ->assertJsonPath('data.tolerancia_minutos', 15)
            ->assertJsonPath('data.mesa.numero', 4)
            ->assertJsonPath('data.subtotal', 90)
            ->assertJsonPath('data.platos.0.nombre', 'Cebiche clásico')
            ->assertJsonPath('data.platos.0.importe', 90)
            ->assertJsonPath('data.historial.0.tipo', 'creacion')
            ->assertJsonPath('data.nombre', 'Lucía Ramírez');

        $this->assertStringContainsString('de', (string) $respuesta->json('data.fecha_larga'));

        $this->assertDatabaseHas('reservas', [
            'folio' => 'HRK-2481',
            'user_id' => $this->cliente->id,
            'creada_por' => null,
            'estado' => 'confirmada',
        ]);
    }

    public function test_sin_mesa_la_reserva_entra_a_la_lista_de_espera(): void
    {
        $datos = $this->crearReserva(['mesa_id' => null]);

        $this->assertSame('lista-espera', $datos['data']['estado']);
        $this->assertNull($datos['data']['mesa']);

        $reserva = Reserva::query()->where('folio', $datos['data']['folio'])->firstOrFail();
        $this->assertNull($reserva->mesa_id);
    }

    public function test_el_siguiente_folio_es_consecutivo(): void
    {
        $this->crearReserva();
        $segundo = $this->crearReserva(['mesa_id' => $this->mesa('5')->id]);

        $this->assertSame('HRK-2482', $segundo['data']['folio']);
    }

    public function test_dos_reservas_a_menos_de_90_minutos_en_la_misma_mesa_son_imposibles(): void
    {
        $this->crearReserva(['hora' => '20:00']);

        $respuesta = $this->withToken($this->token)->postJson('/api/v1/reservas', [
            'fecha' => $this->manana,
            'hora' => '21:00',
            'personas' => 4,
            'mesa_id' => $this->mesa('4')->id,
        ]);

        $respuesta->assertStatus(409)
            ->assertJsonPath('message', 'Esa mesa ya está ocupada en esa franja')
            ->assertJsonPath('motivo', 'conflicto');

        // Devuelve las mesas válidas para esa franja.
        $this->assertNotEmpty($respuesta->json('mesas'));

        // A 90 minutos exactos ya no hay choque.
        $this->withToken($this->token)->postJson('/api/v1/reservas', [
            'fecha' => $this->manana,
            'hora' => '21:30',
            'personas' => 4,
            'mesa_id' => $this->mesa('4')->id,
        ])->assertCreated();
    }

    public function test_una_mesa_inactiva_no_recibe_reservas(): void
    {
        $this->mesa('4')->forceFill(['activa' => false])->save();

        $this->withToken($this->token)->postJson('/api/v1/reservas', [
            'fecha' => $this->manana,
            'hora' => '20:00',
            'personas' => 4,
            'mesa_id' => $this->mesa('4')->id,
        ])->assertStatus(409)
            ->assertJsonPath('motivo', 'mesa-inactiva');
    }

    public function test_la_mesa_debe_tener_capacidad_suficiente(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/reservas', [
            'fecha' => $this->manana,
            'hora' => '20:00',
            'personas' => 6,
            'mesa_id' => $this->mesa('1')->id,
        ])->assertStatus(409)
            ->assertJsonPath('motivo', 'capacidad');
    }

    public function test_un_plato_agotado_no_entra_a_la_preorden(): void
    {
        $chaufa = Plato::query()->where('nombre', 'Chaufa de mariscos')->firstOrFail();

        $this->withToken($this->token)->postJson('/api/v1/reservas', [
            'fecha' => $this->manana,
            'hora' => '20:00',
            'personas' => 2,
            'mesa_id' => $this->mesa('1')->id,
            'platos' => [['plato_id' => $chaufa->id, 'cantidad' => 1]],
        ])->assertStatus(409)
            ->assertJsonPath('motivo', 'plato-no-disponible');

        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_valida_fecha_hora_personas_y_cantidades(): void
    {
        $ayer = Fechas::hoy()->subDay()->toDateString();

        $this->withToken($this->token)->postJson('/api/v1/reservas', [
            'fecha' => $ayer,
            'hora' => '15:15',
            'personas' => 9,
            'platos' => [['plato_id' => 1, 'cantidad' => 0]],
        ])->assertStatus(422)
            ->assertJsonPath('errors.fecha.0', 'Elige una fecha de hoy en adelante.')
            ->assertJsonPath('errors.hora.0', 'Elige un horario de reserva disponible.')
            ->assertJsonPath('errors.personas.0', 'La reserva es de 1 a 6 personas.')
            ->assertJsonValidationErrors(['platos.0.cantidad' => 'La cantidad mínima por plato es 1.']);
    }

    public function test_mis_reservas_separa_proximas_de_pasadas_y_omite_canceladas(): void
    {
        $this->crearReserva();

        Reserva::create([
            'folio' => 'HRK-2490',
            'nombre' => $this->cliente->nombre,
            'correo' => $this->cliente->correo,
            'telefono' => $this->cliente->telefono,
            'fecha' => Fechas::hoy()->subDay()->toDateString(),
            'hora' => '20:00',
            'personas' => 2,
            'mesa_id' => $this->mesa('2')->id,
            'estado' => EstadoReserva::Finalizada,
            'subtotal' => 0,
        ]);

        $cancelada = Reserva::create([
            'folio' => 'HRK-2491',
            'nombre' => $this->cliente->nombre,
            'correo' => $this->cliente->correo,
            'telefono' => $this->cliente->telefono,
            'fecha' => $this->manana,
            'hora' => '13:00',
            'personas' => 2,
            'mesa_id' => $this->mesa('7')->id,
            'estado' => EstadoReserva::Cancelada,
            'subtotal' => 0,
        ]);

        $respuesta = $this->withToken($this->token)->getJson('/api/v1/mis-reservas')->assertOk();

        $respuesta->assertJsonCount(1, 'proximas')
            ->assertJsonCount(1, 'pasadas')
            ->assertJsonPath('pasadas.0.folio', 'HRK-2490');

        $folios = collect($respuesta->json('proximas'))->pluck('folio')->all();
        $this->assertNotContains($cancelada->folio, $folios);
    }

    public function test_el_cliente_cancela_dentro_de_la_ventana_y_queda_en_el_historial(): void
    {
        $datos = $this->crearReserva();
        $folio = $datos['data']['folio'];

        $respuesta = $this->withToken($this->token)->patchJson("/api/v1/reservas/{$folio}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelada')
            ->assertJsonPath('data.historial.1.texto', 'Cancelada por el cliente');

        $this->assertSame('cancelada', $respuesta->json('data.estado'));
        $this->assertDatabaseHas('reserva_historial', [
            'texto' => 'Cancelada por el cliente',
            'autor' => 'Lucía Ramírez',
        ]);
    }

    public function test_no_se_puede_cancelar_dentro_de_las_dos_horas_previas(): void
    {
        $datos = $this->crearReserva();
        $folio = $datos['data']['folio'];

        // Una hora antes del inicio del 26 a las 20:00.
        Carbon::setTestNow(Carbon::parse('2026-11-26 19:00:00', 'America/Lima'));

        $this->withToken($this->token)->patchJson("/api/v1/reservas/{$folio}/cancelar")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Solo puedes cancelar hasta 2 horas antes.');
    }

    public function test_no_se_puede_cancelar_dos_veces(): void
    {
        $folio = $this->crearReserva()['data']['folio'];

        $this->withToken($this->token)->patchJson("/api/v1/reservas/{$folio}/cancelar")->assertOk();

        $this->withToken($this->token)->patchJson("/api/v1/reservas/{$folio}/cancelar")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Esta reserva ya está cancelada.');
    }

    public function test_una_reserva_ajena_no_se_ve_ni_se_cancela(): void
    {
        $folio = $this->crearReserva()['data']['folio'];

        $otro = User::factory()->create(['correo' => 'otro@correo.pe']);

        $this->actuarComo($otro)->getJson("/api/v1/reservas/{$folio}")->assertStatus(403);
        $this->actuarComo($otro)->patchJson("/api/v1/reservas/{$folio}/cancelar")->assertStatus(403);
    }

    public function test_el_admin_puede_ver_cualquier_reserva(): void
    {
        $folio = $this->crearReserva()['data']['folio'];

        $admin = User::query()->where('correo', 'ana.quispe@elhuarike.pe')->firstOrFail();

        $this->actuarComo($admin)->getJson("/api/v1/reservas/{$folio}")
            ->assertOk()
            ->assertJsonPath('data.folio', $folio);
    }

    public function test_un_folio_inexistente_responde_404(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/reservas/HRK-0000')
            ->assertStatus(404)
            ->assertJsonPath('message', 'No encontramos lo que buscabas.');
    }
}
