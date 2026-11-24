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
 * Aviso de cambio de estado desde el panel (docs §8.1): se envía solo cuando
 * la reserva pasa a confirmada o cancelada.
 */
class ReservaEstadoCambiado extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Reserva $reserva,
        public readonly string $autor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reserva {$this->reserva->folio}: {$this->reserva->estado->etiqueta()} · El Huarike",
        );
    }

    public function content(): Content
    {
        $reserva = $this->reserva->loadMissing(['mesa', 'platos']);

        return new Content(
            view: 'mail.reserva-estado',
            with: [
                'reserva' => $reserva,
                'autor' => $this->autor,
                'estado' => $reserva->estado->etiqueta(),
                'datos' => (new ReservaResource($reserva))->resolve(),
            ],
        );
    }
}
