<?php

use App\Models\AppointmentGoogleCalendarEvent;
use App\Models\BarberGoogleCalendarConnection;
use App\Support\GoogleCalendarSync;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('customers:dispatch-reminders')->hourly()->withoutOverlapping();

Schedule::call(function (): void {
    $sync = app(GoogleCalendarSync::class);
    BarberGoogleCalendarConnection::query()
        ->whereNotNull('connected_at')
        ->where('backfill_status', 'pending')
        ->select('id')
        ->chunkById(100, fn ($connections) => $connections->each(fn (BarberGoogleCalendarConnection $connection) => $sync->queueBackfill($connection->id)));

    AppointmentGoogleCalendarEvent::query()
        ->whereIn('sync_status', ['pending', 'error'])
        ->whereHas('connection', fn ($query) => $query->whereNotNull('connected_at')
            ->whereColumn('barber_google_calendar_connections.google_calendar_id', 'appointment_google_calendar_events.google_calendar_id'))
        ->select('id')
        ->chunkById(100, fn ($mappings) => $mappings->each(fn (AppointmentGoogleCalendarEvent $mapping) => $sync->queueAppointment($mapping->id)));
})->everyMinute()->name('google-calendar-reconcile')->withoutOverlapping();
