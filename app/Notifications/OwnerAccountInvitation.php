<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnerAccountInvitation extends Notification
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
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
        $setupUrl = route('owner-invitations.accept', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Invitación para administrar '.$this->barbershopName.' en BarberHub')
            ->greeting('Hola '.$notifiable->name)
            ->line('Te invitamos a administrar '.$this->barbershopName.' como persona propietaria.')
            ->line('Configura tu contraseña y verifica tu correo para activar el acceso a tu cuenta.')
            ->action('Configurar contraseña', $setupUrl)
            ->line('Este enlace es personal, se puede usar una sola vez y vence en '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutos.');
    }
}
