<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class GuestBookingEmailCode extends Notification
{
    public function __construct(#[\SensitiveParameter] public readonly string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifica tu correo para reservar en BarberHub')
            ->greeting('Hola')
            ->line('Usa este código para verificar tu correo electrónico: '.$this->code)
            ->line('El código vence en 10 minutos. Si no solicitaste una reserva, puedes ignorar este mensaje.');
    }
}
