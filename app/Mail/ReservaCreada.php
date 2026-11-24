<?php

namespace App\Mail;

use App\Http\Resources\ReservaResource;
use App\Models\Reserva;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de reserva creada (docs §8.1): folio, fecha, hora, personas, mesa,
 * preorden y subtotal. Se envía al cliente y al correo del restaurante.
 */
class ReservaCreada extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Reserva $reserva) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reserva {$this->reserva->folio} confirmada · El Huarike",
        );
    }

    public function content(): Content
    {
        $reserva = $this->reserva->loadMissing(['mesa', 'platos']);

        return new Content(
            view: 'mail.reserva-creada',
            with: [
                'reserva' => $reserva,
                'datos' => (new ReservaResource($reserva))->resolve(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
