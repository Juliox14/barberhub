<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\User;

final class AppointmentHistory
{
    public function record(
        Appointment $appointment,
        string $event,
        string $actorKind,
        ?User $actor,
        ?string $beforeStartsAt,
        ?string $afterStartsAt,
        ?string $beforeStatus,
        ?string $afterStatus,
    ): AppointmentEvent {
        return AppointmentEvent::query()->create([
            'barbershop_id' => $appointment->barbershop_id,
            'appointment_id' => $appointment->id,
            'customer_id' => $appointment->customer_id,
            'actor_kind' => $actorKind,
            'actor_user_id' => $actor?->id,
            'event_kind' => $event,
            'before_starts_at' => $beforeStartsAt,
            'after_starts_at' => $afterStartsAt,
            'before_status' => $beforeStatus,
            'after_status' => $afterStatus,
        ]);
    }
}
