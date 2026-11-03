<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PublicBookingConfirmed extends Notification
{
    /** @param array<string, mixed> $summary */
    public function __construct(
        public readonly string $shopName,
        public readonly array $summary,
        public readonly ?string $managementUrl = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Tu cita en '.$this->shopName.' está confirmada')
            ->greeting('Hola, '.$this->summary['name'])
            ->line('Tu cita ha sido confirmada automáticamente.')
            ->line('Servicio: '.$this->summary['service'])
            ->line('Profesional: '.$this->summary['barber'])
            ->line('Fecha y hora: '.str_replace('T', ' ', $this->summary['starts_at']).' ('.$this->summary['timezone'].')')
            ->line('Duración: '.$this->summary['duration'].' minutos. Precio: '.$this->summary['price']);

        if ($this->managementUrl !== null) {
            $mail->action('Ver y gestionar tu cita', $this->managementUrl);
        }

        return $mail->line('Para consultas o cambios, contacta directamente a la barbería.');
    }
}
