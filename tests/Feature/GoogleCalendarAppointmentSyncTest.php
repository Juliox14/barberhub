<?php

namespace Tests\Feature;

use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Jobs\SyncBarberGoogleCalendar;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentGoogleCalendarEvent;
use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\BarberGoogleCalendarConnection;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use App\Support\GoogleCalendarClient;
use App\Support\GoogleCalendarDispatcher;
use App\Support\GoogleCalendarSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoogleCalendarAppointmentSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_connected_future_appointment_keeps_a_durable_mapping_for_async_sync(): void
    {
        Queue::fake();
        $this->travelTo('2026-10-01 00:00:00 UTC');
        $barbershop = Barbershop::factory()->create();
        $barber = Barber::factory()->for($barbershop)->create();
        $connection = $barber->googleCalendarConnection()->create([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'google_calendar_id' => 'calendar-id',
            'connected_at' => now(),
        ]);

        $appointment = Appointment::factory()->for($barbershop)->for($barber)->create([
            'starts_at' => '2026-10-05 10:00:00',
            'ends_at' => '2026-10-05 10:30:00',
        ]);

        $this->assertDatabaseHas('appointment_google_calendar_events', [
            'appointment_id' => $appointment->id,
            'connection_id' => $connection->id,
            'google_calendar_id' => 'calendar-id',
            'sync_status' => 'pending',
        ]);
        Queue::assertPushed(SyncAppointmentToGoogleCalendar::class);
    }

    public function test_tenant_booking_route_creates_a_calendar_mapping_after_booking_commit(): void
    {
        Queue::fake();
        $this->travelTo('2026-10-04 08:00:00 America/Mexico_City');
        [$barbershop, $barber, $service, $owner, $connection] = $this->bookingContext();

        $this->actingAs($owner)->post(route('tenant.appointments.store', $barbershop, false), [
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'customer_id' => '',
            'starts_at' => '2026-10-05T10:00',
        ])->assertRedirect();

        $appointment = Appointment::query()->sole();
        $this->assertDatabaseHas('appointment_google_calendar_events', [
            'appointment_id' => $appointment->id,
            'connection_id' => $connection->id,
            'sync_status' => 'pending',
        ]);
        Queue::assertPushed(SyncAppointmentToGoogleCalendar::class);

        $this->actingAs($owner)->put(route('tenant.appointments.reschedule', [$barbershop, $appointment], false), [
            'starts_at' => '2026-10-05T11:00',
        ])->assertRedirect();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'starts_at' => '2026-10-05 11:00:00']);
        $mapping = AppointmentGoogleCalendarEvent::query()->where('appointment_id', $appointment->id)->sole();

        $this->post(route('tenant.appointments.cancel', [$barbershop, $appointment], false))->assertRedirect();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => Appointment::STATUS_CANCELLED]);
        $cancelEvent = AppointmentEvent::query()->where('appointment_id', $appointment->id)->latest('id')->firstOrFail();
        $this->post(route('tenant.appointments.restore', [$barbershop, $appointment], false), [
            'event_id' => $cancelEvent->id,
            'expected_starts_at' => '2026-10-05 11:00:00',
            'expected_status' => Appointment::STATUS_CANCELLED,
            'reason' => 'Restaurar para probar la sincronización',
        ])->assertRedirect();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => Appointment::STATUS_SCHEDULED]);
        $this->assertSame($mapping->id, AppointmentGoogleCalendarEvent::query()->where('appointment_id', $appointment->id)->sole()->id);
    }

    public function test_public_confirmation_survives_after_commit_queue_failure_without_google_requests(): void
    {
        Queue::fake();
        Notification::fake();
        Http::preventStrayRequests();
        $this->travelTo('2026-10-04 08:00:00 America/Mexico_City');
        [$barbershop, $barber, $service, , $connection] = $this->bookingContext();
        $user = User::factory()->create();
        Customer::factory()->for($barbershop)->for($user)->create();
        $this->mock(GoogleCalendarSync::class)
            ->shouldReceive('queueAppointmentAfterCommit')
            ->once()
            ->andThrow(new \RuntimeException('queue unavailable'));
        $this->actingAs($user);
        $summary = $this->postJson(route('public.booking.summary', $barbershop), [
            'service_id' => $service->id,
            'slot' => $barber->id.'|2026-10-05T10:00',
            'name' => 'Cliente',
            'email' => $user->email,
        ])->assertOk();
        $cookie = $summary->getCookie(config('session.cookie'));
        $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue());

        $this->postJson(route('public.booking.store', $barbershop), ['draft_id' => $summary->json('draft_id')])
            ->assertOk()->assertJsonPath('confirmed', true);

        $appointment = Appointment::query()->sole();
        $this->assertDatabaseHas('appointment_google_calendar_events', [
            'appointment_id' => $appointment->id,
            'connection_id' => $connection->id,
            'sync_status' => 'pending',
        ]);
        Http::assertNothingSent();
    }

    public function test_public_booking_route_creates_a_calendar_mapping_for_a_connected_barber(): void
    {
        Queue::fake();
        Notification::fake();
        $this->travelTo('2026-10-04 08:00:00 America/Mexico_City');
        [$barbershop, $barber, $service, , $connection] = $this->bookingContext();
        $user = User::factory()->create();
        Customer::factory()->for($barbershop)->for($user)->create();
        $this->actingAs($user);
        $summary = $this->postJson(route('public.booking.summary', $barbershop), [
            'service_id' => $service->id,
            'slot' => $barber->id.'|2026-10-05T10:00',
            'name' => 'Cliente',
            'email' => $user->email,
        ])->assertOk();
        $cookie = $summary->getCookie(config('session.cookie'));
        $this->withCredentials()->withCookie($cookie->getName(), $cookie->getValue());

        $this->postJson(route('public.booking.store', $barbershop), ['draft_id' => $summary->json('draft_id')])
            ->assertOk()->assertJsonPath('confirmed', true);

        $appointment = Appointment::query()->sole();
        $this->assertDatabaseHas('appointment_google_calendar_events', [
            'appointment_id' => $appointment->id,
            'connection_id' => $connection->id,
            'sync_status' => 'pending',
        ]);
        Queue::assertPushed(SyncAppointmentToGoogleCalendar::class);
    }

    public function test_rolled_back_booking_leaves_no_calendar_mapping(): void
    {
        Queue::fake();
        $barbershop = Barbershop::factory()->create();
        $barber = Barber::factory()->for($barbershop)->create();
        $barber->googleCalendarConnection()->create([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'google_calendar_id' => 'calendar-id',
            'connected_at' => now(),
        ]);

        DB::beginTransaction();
        $appointment = Appointment::factory()->for($barbershop)->for($barber)->create([
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->addMinutes(30)->format('Y-m-d H:i:s'),
        ]);
        DB::rollBack();

        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id]);
        $this->assertDatabaseMissing('appointment_google_calendar_events', ['appointment_id' => $appointment->id]);
        Queue::assertNothingPushed();
    }

    public function test_calendar_transport_failure_is_rethrown_without_provider_or_token_context(): void
    {
        Http::preventStrayRequests();
        Http::fake(['www.googleapis.com/calendar/v3/calendars/calendar-id/events*' => Http::failedConnection()]);
        $client = app(GoogleCalendarClient::class);

        try {
            $client->insertEvent('private-access-token', 'calendar-id', str_repeat('d', 32), ['summary' => 'Appointment']);
            $this->fail('A connection failure should be converted to a sanitized retryable exception.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Google Calendar no está disponible temporalmente.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('private-access-token', $exception->getMessage());
        }
    }

    public function test_worker_inserts_stable_event_with_the_barbershop_wall_clock_timezone(): void
    {
        Http::preventStrayRequests();
        Http::fake(['www.googleapis.com/calendar/v3/calendars/calendar-id/events*' => Http::response(['id' => 'event-id'])]);
        $this->travelTo('2026-10-01 00:00:00 UTC');
        [$appointment, $mapping] = $this->connectedAppointment();

        (new SyncAppointmentToGoogleCalendar($mapping->id))->handle(app(GoogleCalendarClient::class));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['start']['dateTime'] === '2026-10-05T10:00:00-06:00'
            && $request['start']['timeZone'] === 'America/Mexico_City'
            && $request['end']['dateTime'] === '2026-10-05T10:30:00-06:00'
            && ! isset($request['attendees'])
            && is_string($request['id']));
        $this->assertDatabaseHas('appointment_google_calendar_events', [
            'id' => $mapping->id,
            'sync_status' => 'synced',
            'google_event_id' => $mapping->fresh()->google_event_id,
        ]);
    }

    public function test_state_change_during_completion_cannot_be_overwritten_by_stale_sync(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(['www.googleapis.com/calendar/v3/calendars/calendar-id/events*' => Http::response(['id' => 'event-id'])]);
        $this->travelTo('2026-10-01 00:00:00 UTC');
        [$appointment, $mapping] = $this->connectedAppointment();
        $injected = false;
        DB::connection()->beforeExecuting(function (string $query) use ($appointment, &$injected): void {
            if (! $injected
                && str_contains($query, 'update "appointment_google_calendar_events"')
                && str_contains($query, 'sync_status')) {
                $injected = true;
                $appointment->update(['starts_at' => '2026-10-05 11:00:00', 'ends_at' => '2026-10-05 11:30:00']);
            }
        });

        (new SyncAppointmentToGoogleCalendar($mapping->id))->handle(app(GoogleCalendarClient::class));

        $this->assertTrue($injected);
        $this->assertSame(AppointmentGoogleCalendarEvent::STATUS_PENDING, $mapping->fresh()->sync_status);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'starts_at' => '2026-10-05 11:00:00']);

        (new SyncAppointmentToGoogleCalendar($mapping->id))->handle(app(GoogleCalendarClient::class));

        Http::assertSent(fn (Request $request): bool => $request['start']['dateTime'] === '2026-10-05T11:00:00-06:00');
        $this->assertSame(AppointmentGoogleCalendarEvent::STATUS_SYNCED, $mapping->fresh()->sync_status);
    }

    public function test_conflict_reconciles_the_same_stable_event_instead_of_creating_another(): void
    {
        Http::preventStrayRequests();
        Queue::fake();
        Http::fake([
            'www.googleapis.com/calendar/v3/calendars/calendar-id/events*' => Http::sequence()
                ->push(['error' => ['message' => 'already exists']], 409)
                ->push(['id' => 'existing-event']),
        ]);
        $this->travelTo('2026-10-01 00:00:00 UTC');
        [$appointment, $mapping] = $this->connectedAppointment();
        $mapping->forceFill(['google_event_id' => str_repeat('a', 32)])->save();

        (new SyncAppointmentToGoogleCalendar($mapping->id))->handle(app(GoogleCalendarClient::class));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['id'] === str_repeat('a', 32));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT' && $request['id'] === str_repeat('a', 32));
        $this->assertSame(str_repeat('a', 32), $mapping->fresh()->google_event_id);
    }

    public function test_stale_cancellation_job_reads_the_restored_latest_appointment_state(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(['www.googleapis.com/calendar/v3/calendars/calendar-id/events*' => Http::response(['id' => 'restored-event'])]);
        $this->travelTo('2026-10-01 00:00:00 UTC');
        [$appointment, $mapping] = $this->connectedAppointment();
        $mapping->forceFill(['google_event_id' => str_repeat('c', 32)])->save();
        $appointment->update(['status' => Appointment::STATUS_CANCELLED]);
        $appointment->update(['status' => Appointment::STATUS_SCHEDULED, 'starts_at' => '2026-10-05 11:00:00', 'ends_at' => '2026-10-05 11:30:00']);

        (new SyncAppointmentToGoogleCalendar($mapping->id))->handle(app(GoogleCalendarClient::class));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['start']['dateTime'] === '2026-10-05T11:00:00-06:00');
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');
        $this->assertSame('synced', $mapping->fresh()->sync_status);
    }

    public function test_revoked_refresh_token_marks_connection_for_safe_reauthorization(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'private details'], 400)]);
        $this->travelTo('2026-10-01 00:00:00 UTC');
        [$appointment, $mapping] = $this->connectedAppointment();
        $mapping->connection->forceFill(['token_expires_at' => now()->subMinute()])->save();

        (new SyncAppointmentToGoogleCalendar($mapping->id))->handle(app(GoogleCalendarClient::class));

        $this->assertDatabaseHas('barber_google_calendar_connections', [
            'id' => $mapping->connection_id,
            'connected_at' => null,
            'sync_status' => 'reconnect_needed',
            'sync_error' => 'Vuelve a conectar Google Calendar.',
        ]);
        $this->assertStringNotContainsString('private details', serialize($mapping->connection->fresh()->toArray()));

        $barber = $mapping->connection->barber;
        $barberUser = User::factory()->create();
        Membership::factory()->for($barberUser)->for($appointment->barbershop)->create(['role' => Membership::ROLE_BARBER]);
        $barber->update(['user_id' => $barberUser->id]);
        $this->actingAs($barberUser)
            ->get(route('tenant.google-calendar.show', $appointment->barbershop, false))
            ->assertOk()
            ->assertSee('Vuelve a conectar Google Calendar')
            ->assertSee('Conectar de nuevo')
            ->assertSee('Conectar Google Calendar')
            ->assertDontSee('private details');
    }

    public function test_cancelled_mapping_treats_remote_not_found_as_success(): void
    {
        Http::preventStrayRequests();
        Queue::fake();
        Http::fake(['www.googleapis.com/calendar/v3/calendars/calendar-id/events*' => Http::response([], 404)]);
        $this->travelTo('2026-10-01 00:00:00 UTC');
        [$appointment, $mapping] = $this->connectedAppointment();
        $mapping->forceFill(['google_event_id' => str_repeat('b', 32)])->save();
        $appointment->update(['status' => Appointment::STATUS_CANCELLED]);

        (new SyncAppointmentToGoogleCalendar($mapping->id))->handle(app(GoogleCalendarClient::class));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_ends_with($request->url(), str_repeat('b', 32).'?sendUpdates=none'));
        $this->assertDatabaseHas('appointment_google_calendar_events', [
            'id' => $mapping->id,
            'sync_status' => 'synced',
            'google_event_id' => null,
        ]);
    }

    public function test_reconnect_backfill_includes_only_future_scheduled_appointments_and_reconciles_mapped_rows(): void
    {
        Queue::fake();
        $this->travelTo('2026-10-01 00:00:00 UTC');
        [$appointment, $mapping] = $this->connectedAppointment();
        $barber = $appointment->barber;
        $historical = Appointment::factory()->for($appointment->barbershop)->for($barber)->completed()->create([
            'starts_at' => '2026-09-30 10:00:00', 'ends_at' => '2026-09-30 10:30:00',
        ]);
        $cancelled = Appointment::factory()->for($appointment->barbershop)->for($barber)->cancelled()->create([
            'starts_at' => '2026-10-06 10:00:00', 'ends_at' => '2026-10-06 10:30:00',
        ]);
        $cancelledMapping = AppointmentGoogleCalendarEvent::query()->create([
            'appointment_id' => $cancelled->id,
            'connection_id' => $mapping->connection_id,
            'google_calendar_id' => 'calendar-id',
            'google_event_id' => 'old-google-event',
        ]);

        (new SyncBarberGoogleCalendar($mapping->connection_id))->handle(app(GoogleCalendarDispatcher::class));

        Queue::assertPushed(SyncAppointmentToGoogleCalendar::class, 3);
        $this->assertDatabaseMissing('appointment_google_calendar_events', ['appointment_id' => $historical->id]);
        $this->assertDatabaseHas('appointment_google_calendar_events', ['id' => $cancelledMapping->id]);
    }

    public function test_disconnected_appointment_creation_does_not_create_calendar_work(): void
    {
        Queue::fake();
        $appointment = Appointment::factory()->create();

        $this->assertDatabaseMissing('appointment_google_calendar_events', [
            'appointment_id' => $appointment->id,
        ]);
        Queue::assertNothingPushed();
    }

    /** @return array{0: Barbershop, 1: Barber, 2: Service, 3: User, 4: BarberGoogleCalendarConnection} */
    private function bookingContext(): array
    {
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $service = Service::factory()->for($barbershop)->create(['active' => true, 'duration_minutes' => 30]);
        $barber = Barber::factory()->for($barbershop)->create(['active' => true]);
        BusinessHour::factory()->for($barbershop)->create([
            'weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00', 'closed' => false,
        ]);
        BarberAvailability::factory()->for($barber)->create([
            'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '18:00', 'active' => true,
        ]);
        $barber->services()->sync([$service->id]);
        $connection = $barber->googleCalendarConnection()->create([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'google_calendar_id' => 'calendar-id',
            'connected_at' => now(),
        ]);

        return [$barbershop, $barber, $service, $owner, $connection];
    }

    /** @return array{0: Appointment, 1: AppointmentGoogleCalendarEvent} */
    private function connectedAppointment(): array
    {
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $barber = Barber::factory()->for($barbershop)->create();
        $connection = $barber->googleCalendarConnection()->create([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'google_calendar_id' => 'calendar-id',
            'connected_at' => now(),
        ]);
        $appointment = Appointment::factory()->for($barbershop)->for($barber)->create([
            'starts_at' => '2026-10-05 10:00:00',
            'ends_at' => '2026-10-05 10:30:00',
        ]);

        return [$appointment, AppointmentGoogleCalendarEvent::query()->where('appointment_id', $appointment->id)->sole()];
    }
}
