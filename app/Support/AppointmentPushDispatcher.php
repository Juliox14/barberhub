<?php

namespace App\Support;

use App\Jobs\SendAppointmentPush;
use App\Models\Appointment;
use App\Models\User;

final class AppointmentPushDispatcher
{
    /**
     * @param  'assigned'|'rescheduled'|'cancelled'  $event
     */
    public function dispatch(Appointment $appointment, User $actor, string $event): void
    {
        if (! in_array($event, ['assigned', 'rescheduled', 'cancelled'], true)) {
            return;
        }

        $recipientUserId = (int) $appointment->barber()->value('user_id');

        if ($recipientUserId === 0 || $recipientUserId === $actor->id) {
            return;
        }

        if (! config('webpush.public_key') || ! config('webpush.private_key') || ! config('webpush.subject')) {
            return;
        }

        SendAppointmentPush::dispatch($appointment->id, $recipientUserId, $actor->id, $event)->afterCommit();
    }
}
