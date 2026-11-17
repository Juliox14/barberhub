<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Models\AppointmentGoogleCalendarEvent;
use App\Models\BarberGoogleCalendarConnection;
use App\Support\GoogleCalendarClient;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class SyncAppointmentToGoogleCalendar implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 8;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 180, 300];

    public function __construct(public int $mappingId) {}

    public function handle(GoogleCalendarClient $client): void
    {
        $initialMapping = AppointmentGoogleCalendarEvent::query()->find($this->mappingId);
        if ($initialMapping === null) {
            return;
        }

        Cache::lock('google-calendar-connection-'.$initialMapping->connection_id, 60)->block(10, function () use ($client): void {
            $mapping = AppointmentGoogleCalendarEvent::query()->with(['appointment.barbershop', 'appointment.service', 'appointment.barber', 'appointment.customer', 'connection'])->find($this->mappingId);
            $connection = $mapping?->connection;
            if ($mapping === null || $connection === null || $connection->connected_at === null
                || $connection->google_calendar_id !== $mapping->google_calendar_id) {
                return;
            }
            $appointment = $mapping->appointment;
            if ($appointment === null) {
                return;
            }

            $desiredVersion = (int) $mapping->desired_version;
            $accessToken = $this->accessToken($connection, $client);
            if ($accessToken === null) {
                return;
            }

            if ($appointment->status === Appointment::STATUS_CANCELLED) {
                $this->deleteMappedEvent($mapping, $desiredVersion, $connection, $accessToken, $client);

                return;
            }
            if ($appointment->status !== Appointment::STATUS_SCHEDULED
                || $appointment->getRawOriginal('starts_at') <= now($appointment->barbershop->timezone)->format('Y-m-d H:i:s')) {
                $this->completeMapping($mapping, $desiredVersion, [
                    'sync_status' => AppointmentGoogleCalendarEvent::STATUS_SYNCED,
                    'last_error' => null,
                    'last_synced_at' => now(),
                ]);

                return;
            }

            $event = $this->eventPayload($appointment);
            $eventId = $this->ensureEventId($mapping, $desiredVersion);
            if ($eventId === null || ! $this->versionIsCurrent($mapping, $desiredVersion)) {
                return;
            }

            $response = $client->insertEvent($accessToken, $mapping->google_calendar_id, $eventId, $event);
            if ($response->status() === 409) {
                $response = $client->updateEvent($accessToken, $mapping->google_calendar_id, $eventId, $event);
                if (in_array($response->status(), [404, 410], true)) {
                    $eventId = $this->rotateEventId($mapping, $desiredVersion, $eventId);
                    if ($eventId === null) {
                        return;
                    }
                    $response = $client->insertEvent($accessToken, $mapping->google_calendar_id, $eventId, $event);
                }
            } elseif ($response->status() === 404 || $response->status() === 410) {
                $eventId = $this->rotateEventId($mapping, $desiredVersion, $eventId);
                if ($eventId === null) {
                    return;
                }
                $response = $client->insertEvent($accessToken, $mapping->google_calendar_id, $eventId, $event);
            }

            $this->finishResponse($response, $mapping, $desiredVersion, $connection);
        });
    }

    private function accessToken(BarberGoogleCalendarConnection $connection, GoogleCalendarClient $client): ?string
    {
        if ($connection->token_expires_at?->greaterThan(now()->addMinute()) && is_string($connection->access_token)) {
            return $connection->access_token;
        }

        if (! is_string($connection->refresh_token) || $connection->refresh_token === '') {
            $connection->forceFill(['connected_at' => null, 'sync_status' => 'reconnect_needed', 'sync_error' => 'Vuelve a conectar Google Calendar.'])->save();

            return null;
        }

        $expectedRefreshToken = $connection->refresh_token;
        try {
            $response = $client->refreshToken($expectedRefreshToken);
        } catch (Throwable) {
            throw new RuntimeException('Google Calendar no está disponible temporalmente.');
        }
        if ($response === null) {
            throw new RuntimeException('Google Calendar no está disponible temporalmente.');
        }
        if ($response->status() === 401 || $response->json('error') === 'invalid_grant') {
            $connection->forceFill(['connected_at' => null, 'sync_status' => 'reconnect_needed', 'sync_error' => 'Vuelve a conectar Google Calendar.'])->save();

            return null;
        }
        if ($response->status() === 429 || $response->serverError()) {
            throw new RuntimeException('Google Calendar no está disponible temporalmente.');
        }
        $tokens = $response->json();
        if (! $response->successful() || ! is_array($tokens) || ! is_string($tokens['access_token'] ?? null)) {
            $connection->forceFill(['connected_at' => null, 'sync_status' => 'reconnect_needed', 'sync_error' => 'Vuelve a conectar Google Calendar.'])->save();

            return null;
        }

        $connection->refresh();
        if ($connection->connected_at === null || $connection->refresh_token !== $expectedRefreshToken) {
            return null;
        }
        $connection->forceFill([
            'access_token' => $tokens['access_token'],
            'token_expires_at' => now()->addSeconds(max(0, (int) ($tokens['expires_in'] ?? 3600))),
            'sync_status' => 'connected',
            'sync_error' => null,
        ])->save();

        return $tokens['access_token'];
    }

    private function deleteMappedEvent(
        AppointmentGoogleCalendarEvent $mapping,
        int $desiredVersion,
        BarberGoogleCalendarConnection $connection,
        string $accessToken,
        GoogleCalendarClient $client,
    ): void {
        $eventId = $mapping->google_event_id;
        if ($eventId === null) {
            $this->completeMapping($mapping, $desiredVersion, [
                'sync_status' => AppointmentGoogleCalendarEvent::STATUS_SYNCED,
                'last_error' => null,
                'last_synced_at' => now(),
            ]);

            return;
        }

        $response = $client->deleteEvent($accessToken, $mapping->google_calendar_id, $eventId);
        if (in_array($response->status(), [404, 410], true) || $response->successful()) {
            $this->completeMapping($mapping, $desiredVersion, [
                'google_event_id' => null,
                'sync_status' => AppointmentGoogleCalendarEvent::STATUS_SYNCED,
                'last_error' => null,
                'last_synced_at' => now(),
            ]);

            return;
        }
        $this->finishResponse($response, $mapping, $desiredVersion, $connection);
    }

    private function ensureEventId(AppointmentGoogleCalendarEvent $mapping, int $desiredVersion): ?string
    {
        $eventId = $mapping->google_event_id;
        if ($eventId === null) {
            $eventId = bin2hex(random_bytes(16));
            $updated = DB::table('appointment_google_calendar_events')
                ->where('id', $mapping->id)
                ->where('desired_version', $desiredVersion)
                ->whereNull('google_event_id')
                ->update(['google_event_id' => $eventId, 'updated_at' => now()]);
            if ($updated !== 1) {
                return null;
            }
        }

        return $eventId;
    }

    private function rotateEventId(AppointmentGoogleCalendarEvent $mapping, int $desiredVersion, string $oldEventId): ?string
    {
        $newEventId = bin2hex(random_bytes(16));
        $updated = DB::table('appointment_google_calendar_events')
            ->where('id', $mapping->id)
            ->where('desired_version', $desiredVersion)
            ->where('google_event_id', $oldEventId)
            ->update(['google_event_id' => $newEventId, 'updated_at' => now()]);

        return $updated === 1 ? $newEventId : null;
    }

    private function versionIsCurrent(AppointmentGoogleCalendarEvent $mapping, int $desiredVersion): bool
    {
        return AppointmentGoogleCalendarEvent::query()
            ->whereKey($mapping->id)
            ->where('desired_version', $desiredVersion)
            ->exists();
    }

    /** @param array<string, mixed> $attributes */
    private function completeMapping(AppointmentGoogleCalendarEvent $mapping, int $desiredVersion, array $attributes): bool
    {
        $attributes['updated_at'] = now();

        return DB::table('appointment_google_calendar_events')
            ->where('id', $mapping->id)
            ->where('desired_version', $desiredVersion)
            ->update($attributes) === 1;
    }

    private function finishResponse(
        Response $response,
        AppointmentGoogleCalendarEvent $mapping,
        int $desiredVersion,
        BarberGoogleCalendarConnection $connection,
    ): void {
        if ($response->status() === 401) {
            $connection->forceFill(['connected_at' => null, 'sync_status' => 'reconnect_needed', 'sync_error' => 'Vuelve a conectar Google Calendar.'])->save();

            return;
        }
        if ($response->status() === 429 || $response->serverError()) {
            throw new RuntimeException('Google Calendar no está disponible temporalmente.');
        }
        if (! $response->successful()) {
            $this->completeMapping($mapping, $desiredVersion, [
                'sync_status' => AppointmentGoogleCalendarEvent::STATUS_ERROR,
                'last_error' => 'No se pudo sincronizar esta cita.',
            ]);

            return;
        }
        if ($this->completeMapping($mapping, $desiredVersion, [
            'sync_status' => AppointmentGoogleCalendarEvent::STATUS_SYNCED,
            'last_error' => null,
            'last_synced_at' => now(),
        ])) {
            $connection->forceFill(['sync_status' => 'connected', 'sync_error' => null])->save();
        }
    }

    /** @return array<string, mixed> */
    private function eventPayload(Appointment $appointment): array
    {
        $timezone = $appointment->barbershop->timezone;
        $startsAt = Carbon::parse($appointment->getRawOriginal('starts_at'), $timezone);
        $endsAt = Carbon::parse($appointment->getRawOriginal('ends_at'), $timezone);

        $customerName = trim((string) $appointment->customer?->name);
        $summary = $appointment->service->name.' · '.$appointment->barber->display_name;
        $description = 'Cita de BarberHub';

        if ($customerName !== '') {
            $summary .= ' · '.$customerName;
            $description .= "\nCliente: ".$customerName;
        }

        $location = implode(', ', array_filter(
            array_map(static fn (?string $part): string => trim((string) $part), [
                $appointment->barbershop->street_address,
                $appointment->barbershop->city,
                $appointment->barbershop->region,
            ]),
            static fn (string $part): bool => $part !== '',
        ));

        return [
            'summary' => $summary,
            'description' => $description,
            'location' => $location,
            'start' => ['dateTime' => $startsAt->toRfc3339String(), 'timeZone' => $timezone],
            'end' => ['dateTime' => $endsAt->toRfc3339String(), 'timeZone' => $timezone],
        ];
    }
}
