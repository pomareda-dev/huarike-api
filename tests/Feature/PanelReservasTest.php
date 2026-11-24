<?php

namespace Tests\Feature;

use App\Models\Mesa;
use App\Models\Plato;
use App\Models\Reserva;
use App\Models\User;
use App\Support\Fechas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PanelReservasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $hoy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'America/Lima'));

        $this->admin = User::query()->where('correo', 'ana.quispe@elhuarike.pe')->firstOrFail();
        $this->hoy = Fechas::hoy()->toDateString();
    }

    private function reserva(array $overrides = []): Reserva
    {
        $mesa = Mesa::query()->where('numero', $overrides['numeroMesa'] ?? 4)->firstOrFail();

        return Reserva::create(array_merge([
            'folio' => 'HRK-'.random_int(5000, 8999),
            'nombre' => 'Jorge Mendoza',
            'correo' => 'jorge.mendoza@correo.pe',
            'telefono' => '986112233',
            'fecha' => $this->hoy,
            'hora' => '20:00',
            'personas' => 4,
            'mesa_id' => $mesa->id,
            'estado' => 'confirmada',
            'subtotal' => 0,
        ], $overrides));
    }

    public function test_el_listado_filtra_por_fecha_turno_estado_y_texto(): void
    {
        $this->reserva(['folio' => 'HRK-5001', 'hora' => '13:00', 'estado' => 'por-confirmar']);
        $this->reserva(['folio' => 'HRK-5002', 'hora' => '20:00', 'numeroMesa' => 5]);
        $this->reserva([
            'folio' => 'HRK-5003',
            'nombre' => 'Rocío Vega',
            'correo' => 'rocio.vega@correo.pe',
            'numeroMesa' => 2,
            'hora' => '21:00',
            'estado' => 'lista-espera',
            'mesa_id' => null,
        ]);
        $this->reserva(['folio' => 'HRK-5004', 'fecha' => Fechas::hoy()->addDay()->toDateString()]);

        $token = $this->admin->createToken('spa')->plainTextToken;

        // Filtro por fecha.
        $this->withToken($token)->getJson("/api/v1/panel/reservas?fecha={$this->hoy}")
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        // Filtro por turno.
        $this->withToken($token)->getJson("/api/v1/panel/reservas?fecha={$this->hoy}&turno=almuerzo")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.folio', 'HRK-5001');

        // `pendiente` agrupa por-confirmar y lista-espera.
        $this->withToken($token)->getJson('/api/v1/panel/reservas?estado=pendiente')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        // Búsqueda sin tildes.
        $this->withToken($token)->getJson('/api/v1/panel/reservas?texto=ROCIO')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.folio', 'HRK-5003');

        // Búsqueda por mesa y por zona.
        $this->withToken($token)->getJson('/api/v1/panel/reservas?texto=mesa 5')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->withToken($token)->getJson('/api/v1/panel/reservas?texto=terraza')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.folio', 'HRK-5002');
    }

    public function test_el_listado_pagina_de_seis_en_seis_y_trae_el_resumen_del_pie(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->reserva([
                'folio' => 'HRK-6'.sprintf('%03d', $i),
                'personas' => 2,
                'hora' => $i < 4 ? '12:00' : '19:00',
            ]);
        }

        $token = $this->admin->createToken('spa')->plainTextToken;

        $respuesta = $this->withToken($token)->getJson('/api/v1/panel/reservas')->assertOk();

        $respuesta->assertJsonCount(6, 'data')
            ->assertJsonPath('meta.total', 7)
            ->assertJsonPath('meta.total_paginas', 2)
            ->assertJsonPath('meta.resumen.reservas', 7)
            ->assertJsonPath('meta.resumen.personas', 14);
    }

    public function test_el_admin_crea_una_reserva_y_queda_su_nombre_en_creada_por(): void
    {
        $mesa = Mesa::query()->where('numero', 3)->firstOrFail();
        $plato = Plato::query()->where('nombre', 'Cebiche clásico')->firstOrFail();
        $token = $this->admin->createToken('spa')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/panel/reservas', [
            'fecha' => $this->hoy,
            'hora' => '13:00',
            'personas' => 4,
            'mesa_id' => $mesa->id,
            'nombre' => 'Milagros Sosa',
            'correo' => 'Milagros.Sosa@Correo.pe',
            'telefono' => '933 778 990',
            'platos' => [['plato_id' => $plato->id, 'cantidad' => 1]],
        ])->assertCreated()
            ->assertJsonPath('data.creada_por', 'Ana Quispe')
            ->assertJsonPath('data.correo', 'milagros.sosa@correo.pe')
            ->assertJsonPath('data.telefono', '933778990')
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.subtotal', 45);
    }

    public function test_el_alta_del_panel_exige_mesa_y_datos_de_contacto(): void
    {
        $token = $this->admin->createToken('spa')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/panel/reservas', [
            'fecha' => $this->hoy,
            'hora' => '13:00',
            'personas' => 4,
        ])->assertStatus(422)
            ->assertJsonPath('errors.mesa_id.0', 'Elige una mesa para la reserva.')
            ->assertJsonPath('errors.nombre.0', 'Escribe el nombre del cliente.');
    }

    public function test_editar_la_misma_reserva_no_choca_consigo_misma(): void
    {
        $reserva = $this->reserva(['folio' => 'HRK-5100']);
        $token = $this->admin->createToken('spa')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/panel/reservas/{$reserva->folio}", [
            'personas' => 3,
            'notas' => 'Cambió el número de personas.',
        ])->assertOk()
            ->assertJsonPath('data.personas', 3);

        $this->assertDatabaseHas('reserva_historial', [
            'reserva_id' => $reserva->id,
            'tipo' => 'edicion',
            'autor' => 'Ana Quispe',
        ]);
    }

    public function test_editar_hacia_una_franja_ocupada_se_rechaza(): void
    {
        // Misma mesa en dos franjas separadas: mover una sobre la otra choca.
        $this->reserva(['folio' => 'HRK-5101', 'hora' => '20:00', 'numeroMesa' => 5]);
        $otra = $this->reserva(['folio' => 'HRK-5102', 'hora' => '21:00', 'numeroMesa' => 5]);

        $token = $this->admin->createToken('spa')->plainTextToken;

        // Mesa 5 a las 20:00 choca con la mesa 5 a las 21:00 de la otra reserva.
        $this->withToken($token)->patchJson("/api/v1/panel/reservas/{$otra->folio}", [
            'hora' => '20:30',
        ])->assertStatus(409)
            ->assertJsonPath('motivo', 'conflicto');
    }

    public function test_el_cambio_de_estado_deja_historial_con_el_autor(): void
    {
        $reserva = $this->reserva(['folio' => 'HRK-5200', 'estado' => 'por-confirmar']);
        $token = $this->admin->createToken('spa')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/panel/reservas/{$reserva->folio}/estado", [
            'estado' => 'confirmada',
        ])->assertOk()
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.historial.0.texto', 'Confirmada por Ana Quispe')
            ->assertJsonPath('data.historial.0.autor', 'Ana Quispe');
    }

    public function test_una_reserva_pasada_solo_pasa_a_finalizada_o_no_show(): void
    {
        $reserva = $this->reserva([
            'folio' => 'HRK-5300',
            'fecha' => Fechas::hoy()->subDays(2)->toDateString(),
            'estado' => 'confirmada',
        ]);
        $token = $this->admin->createToken('spa')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/panel/reservas/{$reserva->folio}/estado", [
            'estado' => 'por-confirmar',
        ])->assertStatus(409)
            ->assertJsonPath('message', 'Una reserva pasada solo puede marcarse como finalizada o no-show.');

        $this->withToken($token)->patchJson("/api/v1/panel/reservas/{$reserva->folio}/estado", [
            'estado' => 'no-show',
        ])->assertOk()->assertJsonPath('data.estado', 'no-show');
    }

    public function test_no_se_cancela_una_reserva_ya_cancelada_ni_se_repite_el_mismo_estado(): void
    {
        $cancelada = $this->reserva(['folio' => 'HRK-5400', 'estado' => 'cancelada']);
        $confirmada = $this->reserva(['folio' => 'HRK-5401', 'hora' => '21:00', 'numeroMesa' => 5]);
        $token = $this->admin->createToken('spa')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/panel/reservas/{$cancelada->folio}/estado", [
            'estado' => 'cancelada',
        ])->assertStatus(409)
            ->assertJsonPath('message', 'Esta reserva ya está cancelada.');

        $this->withToken($token)->patchJson("/api/v1/panel/reservas/{$confirmada->folio}/estado", [
            'estado' => 'confirmada',
        ])->assertStatus(409)
            ->assertJsonPath('message', 'Esa reserva ya está en ese estado.');
    }

    public function test_el_detalle_trae_historial_y_preorden(): void
    {
        $plato = Plato::query()->where('nombre', 'Cebiche clásico')->firstOrFail();
        $reserva = $this->reserva(['folio' => 'HRK-5500']);
        $reserva->platos()->create([
            'plato_id' => $plato->id,
            'nombre' => $plato->nombre,
            'precio' => $plato->precio,
            'cantidad' => 2,
        ]);

        $token = $this->admin->createToken('spa')->plainTextToken;

        $this->withToken($token)->getJson("/api/v1/panel/reservas/{$reserva->folio}")
            ->assertOk()
            ->assertJsonPath('data.folio', 'HRK-5500')
            ->assertJsonPath('data.platos.0.cantidad', 2)
            ->assertJsonPath('data.mesa.numero', 4);
    }

    public function test_el_csv_de_reservas_tiene_las_columnas_del_prototipo(): void
    {
        $this->reserva([
            'folio' => 'HRK-5600',
            'nombre' => 'Rosa Ttito',
            'correo' => 'rosa.ttito@correo.pe',
            'telefono' => '955334112',
            'hora' => '13:00',
            'estado' => 'cancelada',
        ]);

        $token = $this->admin->createToken('spa')->plainTextToken;

        $respuesta = $this->withToken($token)->get('/api/v1/panel/reservas/export.csv');
        $respuesta->assertOk();

        $contenido = $respuesta->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);
        $this->assertStringContainsString('Folio,Fecha,Hora,Cliente,Teléfono,Correo,Personas,Mesa,Zona,Estado', $contenido);
        $this->assertStringContainsString('HRK-5600', $contenido);
        $this->assertStringContainsString('Mesa 4', $contenido);
        $this->assertStringContainsString('Cancelada', $contenido);
    }

    public function test_un_cliente_no_entra_al_panel_de_reservas(): void
    {
        $cliente = User::query()->where('correo', 'demo@elhuarike.com')->firstOrFail();

        $this->actuarComo($cliente)->getJson('/api/v1/panel/reservas')->assertStatus(403);
        $this->actuarComo($cliente)->getJson('/api/v1/panel/reportes')->assertStatus(403);
        $this->actuarComo($cliente)->getJson('/api/v1/panel/calendario?anio=2026&mes=11')->assertStatus(403);
    }
}
