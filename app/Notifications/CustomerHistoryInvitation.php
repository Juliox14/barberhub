<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerHistoryInvitation extends Notification
{
    public function __construct(
        #[\SensitiveParameter] public string $invitationUrl,
        public string $barbershopName,
    ) {}

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
            ->subject('Tu invitación a '.$this->barbershopName.' en BarberHub')
            ->greeting('Hola')
            ->line($this->barbershopName.' te invita a vincular tu cuenta con tu historial de citas.')
            ->line('Crea tu cuenta o inicia sesión con este correo para confirmar la vinculación. El enlace es personal, de un solo uso y vence en 48 horas.')
            ->action('Vincular mi historial', $this->invitationUrl);
    }
}
