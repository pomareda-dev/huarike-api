<?php

namespace App\Mail;

use App\Http\Resources\ReservaResource;
use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de cancelación (docs §8.1), con el folio y el autor de la acción.
 */
class ReservaCancelada extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Reserva $reserva,
        public readonly string $autor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reserva {$this->reserva->folio} cancelada · El Huarike",
        );
    }

    public function content(): Content
    {
        $reserva = $this->reserva->loadMissing(['mesa', 'platos']);

        return new Content(
            view: 'mail.reserva-cancelada',
            with: [
                'reserva' => $reserva,
                'autor' => $this->autor,
                'datos' => (new ReservaResource($reserva))->resolve(),
            ],
        );
    }
}
