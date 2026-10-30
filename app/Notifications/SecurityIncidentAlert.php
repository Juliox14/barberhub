<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityIncidentAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public const TITLES = [
        'security.account_locked' => 'Cuenta bloqueada temporalmente',
        'security.session_revoked' => 'Sesiones revocadas',
        'platform.barbershop.module_suspended' => 'Módulo suspendido temporalmente',
        'platform.barbershop.module_released' => 'Restricción de módulo levantada',
    ];

    public function __construct(public string $event, public string $context, public string $reason)
    {
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Alerta de seguridad · BarberHub')
            ->greeting('Aviso al responsable de seguridad')
            ->line(self::TITLES[$this->event])
            ->line($this->context)
            ->line($this->reason)
            ->line('Las repeticiones del mismo evento y alcance se agrupan durante 15 minutos para el correo. Consultá el historial para ver todas las acciones.')
            ->action('Revisar bitácora', route('platform.security-audit-logs.index'));
    }
}
