<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Support\GoogleCalendarDispatcher;

final class AppointmentGoogleCalendarObserver
{
    public function created(Appointment $appointment): void
    {
        if ($appointment->status === Appointment::STATUS_SCHEDULED) {
            app(GoogleCalendarDispatcher::class)->dispatch($appointment);
        }
    }

    public function updated(Appointment $appointment): void
    {
        if ($appointment->wasChanged(['starts_at', 'ends_at', 'status'])
            && in_array($appointment->status, [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CANCELLED], true)) {
            app(GoogleCalendarDispatcher::class)->dispatch($appointment);
        }
    }
}
