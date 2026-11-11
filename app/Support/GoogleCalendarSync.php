<?php

namespace App\Support;

use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Jobs\SyncBarberGoogleCalendar;
use Illuminate\Support\Facades\DB;
use Throwable;

class GoogleCalendarSync
{
    public function queueAppointmentAfterCommit(int $mappingId): void
    {
        DB::afterCommit(function () use ($mappingId): void {
            try {
                SyncAppointmentToGoogleCalendar::dispatch($mappingId);
            } catch (Throwable) {
                // The durable mapping is retried by the scheduled reconciler.
            }
        });
    }

    public function queueAppointment(int $mappingId): void
    {
        try {
            SyncAppointmentToGoogleCalendar::dispatch($mappingId);
        } catch (Throwable) {
            // The durable mapping is retried by the scheduled reconciler.
        }
    }

    public function queueBackfill(int $connectionId): void
    {
        try {
            SyncBarberGoogleCalendar::dispatch($connectionId);
        } catch (Throwable) {
            // The durable connection marker is retried by the scheduled reconciler.
        }
    }
}
