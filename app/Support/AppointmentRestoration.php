<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AppointmentRestoration
{
    /** @var array<int, string> */
    private const RECOVERABLE_EVENT_KINDS = [
        'rescheduled',
        'cancelled',
        'completed',
        'no_show',
        'restored',
    ];

    public function findRecoverableEvent(Appointment $appointment, int $eventId): AppointmentEvent
    {
        $event = $appointment->events()
            ->where('barbershop_id', $appointment->barbershop_id)
            ->whereKey($eventId)
            ->firstOrFail();

        abort_unless($this->isRecoverable($event), 404);

        return $event;
    }

    public function isRecoverable(AppointmentEvent $event): bool
    {
        return in_array($event->event_kind, self::RECOVERABLE_EVENT_KINDS, true)
            && $event->before_starts_at !== null
            && $event->after_starts_at !== null
            && $this->isKnownStatus($event->before_status)
            && $this->isKnownStatus($event->after_status);
    }

    private function isKnownStatus(?string $status): bool
    {
        return in_array($status, [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_NO_SHOW,
        ], true);
    }

    /**
     * @param  array{event_id: int, expected_starts_at: string, expected_status: string, reason: string}  $validated
     */
    public function restore(
        Barbershop $barbershop,
        Appointment $appointment,
        AppointmentEvent $sourceEvent,
        array $validated,
        User $actor,
        AppointmentScheduler $scheduler,
        AppointmentHistory $history,
        SecurityAuditLogger $auditLogger,
    ): Appointment {
        return DB::transaction(function () use ($barbershop, $appointment, $sourceEvent, $validated, $actor, $scheduler, $history, $auditLogger): Appointment {
            $barber = Barber::query()->whereKey($appointment->barber_id)->lockForUpdate()->firstOrFail();
            $lockedAppointment = $barbershop->appointments()
                ->whereKey($appointment->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedEvent = $lockedAppointment->events()
                ->where('barbershop_id', $barbershop->id)
                ->whereKey($sourceEvent->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($this->isRecoverable($lockedEvent), 404);

            $currentStartsAt = $lockedAppointment->getRawOriginal('starts_at');
            $currentStatus = $lockedAppointment->status;
            if ($currentStartsAt !== $validated['expected_starts_at']
                || $currentStatus !== $validated['expected_status']
                || $currentStartsAt !== $lockedEvent->getRawOriginal('after_starts_at')
                || $currentStatus !== $lockedEvent->after_status) {
                throw ValidationException::withMessages([
                    'event_id' => 'La cita cambió desde que se mostró la vista previa. Actualiza la página e inténtalo de nuevo.',
                ]);
            }

            abort_unless($lockedAppointment->barbershop_id === $barbershop->id, 404);
            abort_unless($lockedAppointment->barber_id === $barber->id, 404);
            abort_unless($barber->barbershop_id === $barbershop->id, 404);
            $service = $barbershop->services()->whereKey($lockedAppointment->service_id)->firstOrFail();
            if ($lockedAppointment->customer_id !== null) {
                $barbershop->customers()->whereKey($lockedAppointment->customer_id)->firstOrFail();
            }
            $targetStartsAt = Carbon::parse($lockedEvent->getRawOriginal('before_starts_at'), $barbershop->timezone);

            if ($lockedEvent->before_status === Appointment::STATUS_SCHEDULED) {
                $lockedAppointment->update(['status' => Appointment::STATUS_SCHEDULED]);
                $restored = $scheduler->reschedule($lockedAppointment, $targetStartsAt, requireFutureStart: false);
            } else {
                $lockedAppointment->update([
                    'starts_at' => $targetStartsAt,
                    'ends_at' => $targetStartsAt->copy()->addMinutes((int) $service->duration_minutes),
                ]);
                $restored = $lockedAppointment;
            }
            $restored->update(['status' => $lockedEvent->before_status]);

            $history->record(
                $restored,
                'restored',
                'staff',
                $actor,
                $currentStartsAt,
                $restored->getRawOriginal('starts_at'),
                $currentStatus,
                $restored->status,
            );
            $auditLogger->record(
                'appointment.restored',
                actor: $actor,
                barbershopId: $barbershop->id,
                metadata: [
                    'appointment_id' => (string) $restored->id,
                    'source_event_id' => (string) $lockedEvent->id,
                    'reason' => $validated['reason'],
                ],
            );

            return $restored;
        });
    }
}
