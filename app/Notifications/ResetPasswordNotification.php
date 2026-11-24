<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enlace de recuperación de contraseña hacia el SPA (docs §4 A5):
 * `FRONTEND_URL/reset-password?token=…&correo=…`, token vigente 60 minutos.
 * Se encola para no bloquear la respuesta HTTP.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = sprintf(
            '%s/reset-password?token=%s&correo=%s',
            config('huarike.frontend_url'),
            urlencode($this->token),
            urlencode($notifiable->getEmailForPasswordReset()),
        );

        return (new MailMessage)
            ->subject('Restablece tu contraseña · El Huarike')
            ->greeting('Hola '.$notifiable->nombre)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta en El Huarike.')
            ->action('Elegir nueva contraseña', $url)
            ->line('El enlace vence en 60 minutos y solo puede usarse una vez.')
            ->line('Si no fuiste tú, ignora este correo: tu contraseña actual sigue funcionando.')
            ->salutation('Equipo El Huarike');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
