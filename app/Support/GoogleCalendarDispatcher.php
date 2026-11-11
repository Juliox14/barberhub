<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\AppointmentGoogleCalendarEvent;
use App\Models\BarberGoogleCalendarConnection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class GoogleCalendarDispatcher
{
    public function dispatch(Appointment $appointment): void
    {
        $existingMapping = AppointmentGoogleCalendarEvent::query()->where('appointment_id', $appointment->id)->exists();
        if ($appointment->status === Appointment::STATUS_CANCELLED && ! $existingMapping) {
            return;
        }

        $connection = BarberGoogleCalendarConnection::query()
            ->where('barber_id', $appointment->barber_id)
            ->whereNotNull('connected_at')
            ->whereNotNull('google_calendar_id')
            ->first();

        if ($connection === null) {
            return;
        }

        $mapping = AppointmentGoogleCalendarEvent::query()->firstOrCreate([
            'appointment_id' => $appointment->id,
            'connection_id' => $connection->id,
            'google_calendar_id' => $connection->google_calendar_id,
        ], ['sync_status' => AppointmentGoogleCalendarEvent::STATUS_PENDING]);

        DB::table('appointment_google_calendar_events')
            ->where('id', $mapping->id)
            ->increment('desired_version', 1, [
                'sync_status' => AppointmentGoogleCalendarEvent::STATUS_PENDING,
                'last_error' => null,
                'updated_at' => now(),
            ]);

        try {
            app(GoogleCalendarSync::class)->queueAppointmentAfterCommit($mapping->id);
        } catch (Throwable) {
            // The committed mapping remains pending for scheduled recovery.
        }
    }

    public function backfill(BarberGoogleCalendarConnection $connection): void
    {
        $connection->refresh();
        if ($connection->connected_at === null || $connection->google_calendar_id === null) {
            return;
        }

        $connectionId = (int) $connection->id;
        $calendarId = $connection->google_calendar_id;
        $barber = $connection->barber;
        $timezone = $barber->barbershop->timezone;
        $futureAppointmentIds = $barber->appointments()
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('starts_at', '>', now($timezone)->format('Y-m-d H:i:s'))
            ->pluck('id');
        $mappedAppointmentIds = AppointmentGoogleCalendarEvent::query()
            ->where('connection_id', $connectionId)
            ->where('google_calendar_id', $calendarId)
            ->pluck('appointment_id');

        foreach ($futureAppointmentIds->merge($mappedAppointmentIds)->unique() as $appointmentId) {
            $appointment = Appointment::query()->find($appointmentId);
            if ($appointment === null) {
                continue;
            }

            $mapping = AppointmentGoogleCalendarEvent::query()->firstOrCreate([
                'appointment_id' => $appointment->id,
                'connection_id' => $connectionId,
                'google_calendar_id' => $calendarId,
            ], ['sync_status' => AppointmentGoogleCalendarEvent::STATUS_PENDING]);
            DB::table('appointment_google_calendar_events')
                ->where('id', $mapping->id)
                ->increment('desired_version', 1, [
                    'sync_status' => AppointmentGoogleCalendarEvent::STATUS_PENDING,
                    'last_error' => null,
                    'updated_at' => now(),
                ]);

            try {
                app(GoogleCalendarSync::class)->queueAppointment($mapping->id);
            } catch (Throwable) {
                // The durable pending mapping is recovered by the scheduled reconciler.
            }
        }

        DB::table('barber_google_calendar_connections')
            ->where('id', $connectionId)
            ->where('google_calendar_id', $calendarId)
            ->whereNotNull('connected_at')
            ->where('backfill_status', 'pending')
            ->update(['backfill_status' => 'complete', 'updated_at' => now()]);
    }
}
