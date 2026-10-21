<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Membership;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\TenantDashboard;
use App\Support\WebPushSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendAppointmentPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 30];

    /**
     * @param  'assigned'|'rescheduled'|'cancelled'  $event
     */
    public function __construct(
        public int $appointmentId,
        public int $recipientUserId,
        public int $actorUserId,
        public string $event,
    ) {}

    public function handle(WebPushSender $sender): void
    {
        if (! in_array($this->event, ['assigned', 'rescheduled', 'cancelled'], true)
            || $this->actorUserId === $this->recipientUserId) {
            return;
        }

        $appointment = Appointment::query()->with('barbershop')->find($this->appointmentId);

        if ($appointment === null || ! $this->matchesCurrentAppointment($appointment)) {
            return;
        }

        $barber = Barber::query()
            ->whereKey($appointment->barber_id)
            ->where('barbershop_id', $appointment->barbershop_id)
            ->where('user_id', $this->recipientUserId)
            ->where('active', true)
            ->first();

        if ($barber === null) {
            return;
        }

        $recipient = User::query()->find($this->recipientUserId);
        $membership = $recipient === null ? null : TenantDashboard::membershipFor($recipient, $appointment->barbershop);

        if ($membership?->role !== Membership::ROLE_BARBER) {
            return;
        }

        PushSubscription::query()
            ->where('user_id', $this->recipientUserId)
            ->each(fn (PushSubscription $subscription) => $sender->send($subscription, $this->payload($appointment)));
    }

    private function matchesCurrentAppointment(Appointment $appointment): bool
    {
        return match ($this->event) {
            'assigned', 'rescheduled' => $appointment->status === Appointment::STATUS_SCHEDULED,
            'cancelled' => $appointment->status === Appointment::STATUS_CANCELLED,
            default => false,
        };
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    private function payload(Appointment $appointment): array
    {
        [$title, $body] = match ($this->event) {
            'assigned' => ['Nueva cita asignada', 'Tienes una cita nueva en tu agenda.'],
            'rescheduled' => ['Cita reprogramada', 'Una cita de tu agenda fue reprogramada.'],
            'cancelled' => ['Cita cancelada', 'Una cita de tu agenda fue cancelada.'],
        };

        return [
            'title' => $title,
            'body' => $body,
            'url' => route('tenant.appointments.index', [
                'barbershop' => $appointment->barbershop,
                'date' => $appointment->starts_at->timezone($appointment->barbershop->timezone)->toDateString(),
            ], false),
        ];
    }
}
