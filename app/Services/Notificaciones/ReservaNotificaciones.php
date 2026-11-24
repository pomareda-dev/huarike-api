<?php

namespace App\Services\Notificaciones;

use App\Enums\EstadoReserva;
use App\Mail\ReservaCancelada;
use App\Mail\ReservaCreada;
use App\Mail\ReservaEstadoCambiado;
use App\Models\Reserva;
use Illuminate\Support\Facades\Mail;

/**
 * Envío de correos de reserva en cola (docs §8.1). Nunca bloquea la respuesta
 * HTTP: todos los Mailables implementan `ShouldQueue`.
 */
class ReservaNotificaciones
{
    /**
     * Cliente + correo del restaurante.
     */
    public function creada(Reserva $reserva): void
    {
        $restaurante = (string) config('huarike.correo_restaurante');

        Mail::to($reserva->correo)
            ->cc($restaurante)
            ->queue(new ReservaCreada($reserva));
    }

    public function cancelada(Reserva $reserva, string $autor): void
    {
        Mail::to($reserva->correo)->queue(new ReservaCancelada($reserva, $autor));
    }

    /**
     * Solo confirmada y cancelada avisan al cliente.
     */
    public function estadoCambiado(Reserva $reserva, string $autor): void
    {
        if (! in_array($reserva->estado, [EstadoReserva::Confirmada, EstadoReserva::Cancelada], true)) {
            return;
        }

        Mail::to($reserva->correo)->queue(new ReservaEstadoCambiado($reserva, $autor));
    }
}
