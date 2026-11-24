<?php

namespace Tests\Feature;

use App\Mail\ReservaCancelada;
use App\Mail\ReservaCreada;
use App\Mail\ReservaEstadoCambiado;
use App\Models\Mesa;
use App\Models\Plato;
use App\Models\Reserva;
use App\Models\User;
use App\Support\Fechas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailsReservaTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    private User $admin;

    private string $manana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00', 'America/Lima'));
        Mail::fake();

        $this->cliente = User::query()->where('correo', 'demo@elhuarike.com')->firstOrFail();
        $this->admin = User::query()->where('correo', 'ana.quispe@elhuarike.pe')->firstOrFail();
        $this->manana = Fechas::hoy()->addDay()->toDateString();
    }

    private function crearReserva(): string
    {
        return $this->actuarComo($this->cliente)->postJson('/api/v1/reservas', [
            'fecha' => $this->manana,
            'hora' => '20:00',
            'personas' => 2,
            'mesa_id' => Mesa::query()->where('numero', 1)->value('id'),
            'platos' => [
                ['plato_id' => Plato::query()->where('nombre', 'Cebiche clásico')->value('id'), 'cantidad' => 1],
            ],
        ])->assertCreated()->json('data.folio');
    }

    public function test_la_reserva_creada_envia_correo_al_cliente_y_al_restaurante(): void
    {
        $folio = $this->crearReserva();

        Mail::assertQueued(ReservaCreada::class, function (ReservaCreada $mail) use ($folio): bool {
            return $mail->reserva->folio === $folio
                && in_array('demo@elhuarike.com', array_column($mail->to, 'address'), true);
        });

        // El restaurante va en copia.
        Mail::assertQueued(ReservaCreada::class, function (ReservaCreada $mail): bool {
            return in_array(config('huarike.correo_restaurante'), array_column($mail->cc, 'address'), true);
        });
    }

    public function test_la_cancelacion_del_cliente_envia_correo(): void
    {
        $folio = $this->crearReserva();

        $this->actuarComo($this->cliente)->patchJson("/api/v1/reservas/{$folio}/cancelar")->assertOk();

        Mail::assertQueued(ReservaCancelada::class, function (ReservaCancelada $mail) use ($folio): bool {
            return $mail->reserva->folio === $folio && $mail->autor === 'Lucía Ramírez';
        });
    }

    public function test_el_cambio_de_estado_avisa_solo_si_es_confirmada_o_cancelada(): void
    {
        $reserva = Reserva::create([
            'folio' => 'HRK-8801',
            'nombre' => 'Jorge Mendoza',
            'correo' => 'jorge.mendoza@correo.pe',
            'telefono' => '986112233',
            'fecha' => $this->manana,
            'hora' => '13:00',
            'personas' => 2,
            'mesa_id' => Mesa::query()->where('numero', 2)->value('id'),
            'estado' => 'por-confirmar',
            'subtotal' => 0,
        ]);

        $this->actuarComo($this->admin)
            ->patchJson("/api/v1/panel/reservas/{$reserva->folio}/estado", ['estado' => 'finalizada'])
            ->assertOk();

        Mail::assertNotQueued(ReservaEstadoCambiado::class);

        $this->actuarComo($this->admin)
            ->patchJson("/api/v1/panel/reservas/{$reserva->folio}/estado", ['estado' => 'confirmada'])
            ->assertOk();

        Mail::assertQueued(ReservaEstadoCambiado::class, function (ReservaEstadoCambiado $mail) use ($reserva): bool {
            return $mail->reserva->folio === $reserva->folio && $mail->autor === 'Ana Quispe';
        });
    }

    public function test_la_reserva_creada_desde_el_panel_tambien_notifica(): void
    {
        $this->actuarComo($this->admin)->postJson('/api/v1/panel/reservas', [
            'fecha' => $this->manana,
            'hora' => '13:00',
            'personas' => 2,
            'mesa_id' => Mesa::query()->where('numero', 2)->value('id'),
            'nombre' => 'Rosa Ttito',
            'correo' => 'rosa.ttito@correo.pe',
            'telefono' => '955334112',
        ])->assertCreated();

        Mail::assertQueued(ReservaCreada::class, function (ReservaCreada $mail): bool {
            return $mail->reserva->correo === 'rosa.ttito@correo.pe';
        });
    }

    public function test_los_mailables_de_reserva_se_encolan(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new ReservaCreada(new Reserva));
        $this->assertInstanceOf(ShouldQueue::class, new ReservaCancelada(new Reserva, 'Ana Quispe'));
    }
}
