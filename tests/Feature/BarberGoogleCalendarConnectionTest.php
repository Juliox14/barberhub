<?php

namespace Tests\Feature;

use App\Jobs\SyncAppointmentToGoogleCalendar;
use App\Jobs\SyncBarberGoogleCalendar;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberGoogleCalendarConnection;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Support\GoogleCalendarDispatcher;
use App\Support\GoogleCalendarSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class BarberGoogleCalendarConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_barber_sees_google_calendar_navigation_at_the_bottom_of_the_sidebar(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);

        $response = $this->actingAs($barberUser)->get(route('tenant.dashboard', $barbershop, false));
        $content = $response->getContent();
        $labelPosition = strpos($content, 'Conectar Google Calendar');
        $footerPosition = strrpos($content, $barbershop->name);

        $response->assertOk()
            ->assertSee('Conectar Google Calendar')
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('Continuar con Google')
            ->assertDontSee('href="'.route('tenant.google-calendar.show', $barbershop, false).'"', false);
        $this->assertStringContainsString('images/icons/google-calendar.png', $content);
        $this->assertStringContainsString('/google-calendar/connect"', $content);
        $this->assertIsInt($labelPosition);
        $this->assertIsInt($footerPosition);
        $this->assertLessThan($footerPosition, $labelPosition);
    }

    public function test_owner_admin_and_platform_admin_do_not_see_calendar_navigation_without_their_own_barber_profile(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = $this->createMember($barbershop, Membership::ROLE_OWNER);
        $admin = $this->createMember($barbershop, Membership::ROLE_ADMIN);
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        foreach ([$owner, $admin, $platformAdmin] as $user) {
            $this->actingAs($user)
                ->get(route('tenant.dashboard', $barbershop, false))
                ->assertOk()
                ->assertDontSee('Conectar Google Calendar');
        }
    }

    public function test_connected_barber_sees_connected_navigation_and_can_view_disconnect_status(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $barber = $barbershop->barbers()->where('user_id', $barberUser->getKey())->firstOrFail();
        $barber->googleCalendarConnection()->create([
            'access_token' => 'secret-access',
            'refresh_token' => 'secret-refresh',
            'google_calendar_id' => 'calendar-id',
            'connected_at' => now(),
        ]);

        $this->actingAs($barberUser)
            ->get(route('tenant.dashboard', $barbershop, false))
            ->assertOk()
            ->assertSee('Google Calendar conectado')
            ->assertDontSee('Conectar Google Calendar')
            ->assertSee('bh-surface mt-auto p-4', false);

        $this->get(route('tenant.google-calendar.show', $barbershop, false))
            ->assertOk()
            ->assertSee('Desconectar Google Calendar');
    }

    public function test_reconnect_needed_state_shows_a_safe_recovery_prompt_in_the_modal(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $barbershop->barbers()->firstOrFail()->googleCalendarConnection()->create([
            'sync_status' => 'reconnect_needed',
            'sync_error' => 'private provider details',
        ]);

        $this->actingAs($barberUser)
            ->get(route('tenant.dashboard', $barbershop, false))
            ->assertOk()
            ->assertSee('La autorización de Google necesita renovarse.')
            ->assertSee('Continuar con Google')
            ->assertSee('title="Conectar Google Calendar"', false)
            ->assertDontSee('private provider details');
    }

    public function test_barber_is_redirected_to_google_with_an_expiring_session_bound_state(): void
    {
        config([
            'services.google_calendar.client_id' => 'client-id',
            'services.google_calendar.client_secret' => 'client-secret',
            'services.google_calendar.redirect' => 'https://app.example.test/google-calendar/callback',
        ]);
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);

        $response = $this->actingAs($barberUser)->post(route('tenant.google-calendar.connect', $barbershop, false));

        $response->assertRedirect();
        $authorization = parse_url($response->headers->get('Location'));
        parse_str($authorization['query'], $query);
        $this->assertSame('https://accounts.google.com', $authorization['scheme'].'://'.$authorization['host']);
        $this->assertSame('client-id', $query['client_id']);
        $this->assertSame('https://app.example.test/google-calendar/callback', $query['redirect_uri']);
        $this->assertSame('https://www.googleapis.com/auth/calendar.app.created', $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertSame(64, strlen($query['state']));
        $this->assertSame($query['state'], session('google_calendar_oauth_state.state'));
        $this->assertSame((int) $barberUser->getKey(), session('google_calendar_oauth_state.user_id'));
        $this->assertSame((int) $barbershop->barbers()->first()->getKey(), session('google_calendar_oauth_state.barber_id'));
    }

    public function test_missing_credentials_do_not_start_oauth_or_expose_secrets(): void
    {
        config(['services.google_calendar.client_id' => null, 'services.google_calendar.client_secret' => null]);
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);

        $this->actingAs($barberUser)
            ->post(route('tenant.google-calendar.connect', $barbershop, false))
            ->assertRedirect(route('tenant.google-calendar.show', $barbershop, false))
            ->assertSessionHas('calendar_error');

        $this->assertNull(session('google_calendar_oauth_state'));
    }

    public function test_invalid_or_replayed_state_is_rejected_before_google_requests(): void
    {
        Http::preventStrayRequests();
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $session = [
            'state' => 'valid-state',
            'user_id' => $barberUser->getKey(),
            'barber_id' => $barbershop->barbers()->first()->getKey(),
            'barbershop_id' => $barbershop->getKey(),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ];

        $this->actingAs($barberUser)->withSession(['google_calendar_oauth_state' => $session])
            ->get(route('tenant.google-calendar.callback', ['state' => 'wrong', 'code' => 'private-code'], false))
            ->assertRedirect(route('dashboard', [], false));
        $this->assertNull(session('google_calendar_oauth_state'));

        $this->get(route('tenant.google-calendar.callback', ['state' => 'valid-state', 'code' => 'private-code'], false))
            ->assertRedirect(route('dashboard', [], false));
        Http::assertNothingSent();
    }

    public function test_callback_rejects_state_after_tenant_membership_is_revoked(): void
    {
        Http::preventStrayRequests();
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $membership = $barberUser->memberships()->where('barbershop_id', $barbershop->getKey())->firstOrFail();
        $this->configureCallbackState($barbershop, $barberUser, 'revoked-state');
        $membership->update(['status' => Membership::STATUS_INACTIVE]);

        $this->actingAs($barberUser)
            ->get(route('tenant.google-calendar.callback', ['state' => 'revoked-state', 'code' => 'private-code'], false))
            ->assertRedirect(route('dashboard', [], false));

        $this->assertDatabaseCount('barber_google_calendar_connections', 0);
        Http::assertNothingSent();
    }

    public function test_state_bound_to_another_authenticated_user_is_rejected(): void
    {
        Http::preventStrayRequests();
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $differentUser = User::factory()->create();
        $state = [
            'state' => 'user-bound-state',
            'user_id' => $barberUser->getKey(),
            'barber_id' => $barbershop->barbers()->first()->getKey(),
            'barbershop_id' => $barbershop->getKey(),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ];

        $this->actingAs($differentUser)->withSession(['google_calendar_oauth_state' => $state])
            ->get(route('tenant.google-calendar.callback', ['state' => 'user-bound-state', 'code' => 'private-code'], false))
            ->assertRedirect(route('dashboard', [], false));
        Http::assertNothingSent();
    }

    public function test_expired_state_is_rejected_without_exchange(): void
    {
        Http::preventStrayRequests();
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $state = [
            'state' => 'expired-state',
            'user_id' => $barberUser->getKey(),
            'barber_id' => $barbershop->barbers()->first()->getKey(),
            'barbershop_id' => $barbershop->getKey(),
            'expires_at' => now()->subSecond()->timestamp,
        ];

        $this->actingAs($barberUser)->withSession(['google_calendar_oauth_state' => $state])
            ->get(route('tenant.google-calendar.callback', ['state' => 'expired-state', 'code' => 'private-code'], false))
            ->assertRedirect(route('dashboard', [], false));
    }

    public function test_callback_stores_encrypted_credentials_only_after_scope_and_calendar_provisioning_succeed(): void
    {
        Queue::fake([SyncBarberGoogleCalendar::class]);
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'secret-access',
                'refresh_token' => 'secret-refresh',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/calendar.app.created',
            ]),
            'www.googleapis.com/calendar/v3/calendars' => Http::response(['id' => 'calendar-id'], 200),
        ]);
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $barberUser = $this->createBarber($barbershop);
        $this->configureCallbackState($barbershop, $barberUser, 'valid-state');

        $this->actingAs($barberUser)
            ->get(route('tenant.google-calendar.callback', ['state' => 'valid-state', 'code' => 'private-code'], false))
            ->assertRedirect(route('tenant.google-calendar.show', $barbershop, false))
            ->assertSessionHas('status');

        $connection = BarberGoogleCalendarConnection::query()->sole();
        Queue::assertPushed(SyncBarberGoogleCalendar::class, fn (SyncBarberGoogleCalendar $job): bool => $job->connectionId === $connection->id);
        $this->assertSame('secret-access', $connection->access_token);
        $this->assertSame('secret-refresh', $connection->refresh_token);
        $this->assertSame('calendar-id', $connection->google_calendar_id);
        $this->assertSame('pending', $connection->backfill_status);
        $this->assertNotNull($connection->connected_at);
        $this->assertStringNotContainsString('secret-access', $connection->getRawOriginal('access_token'));
        $this->assertStringNotContainsString('secret-refresh', $connection->getRawOriginal('refresh_token'));
        $this->assertArrayNotHasKey('access_token', $connection->toArray());
        $this->assertArrayNotHasKey('refresh_token', $connection->toArray());
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.googleapis.com/calendar/v3/calendars'
            && $request['timeZone'] === 'America/Mexico_City'
            && $request->hasHeader('Authorization', 'Bearer secret-access'));
    }

    public function test_failed_initial_backfill_enqueue_keeps_connection_marker_for_scheduler_recovery(): void
    {
        $this->travelTo('2026-10-01 00:00:00 UTC');
        $this->mock(GoogleCalendarSync::class)
            ->shouldReceive('queueBackfill')
            ->once()
            ->andThrow(new \RuntimeException('queue unavailable'));
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'secret-access',
                'refresh_token' => 'secret-refresh',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/calendar.app.created',
            ]),
            'www.googleapis.com/calendar/v3/calendars' => Http::response(['id' => 'calendar-id']),
        ]);
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $barberUser = $this->createBarber($barbershop);
        $barber = $barbershop->barbers()->firstOrFail();
        $appointment = Appointment::factory()->for($barbershop)->for($barber)->create([
            'starts_at' => '2026-10-05 10:00:00', 'ends_at' => '2026-10-05 10:30:00',
        ]);
        $this->configureCallbackState($barbershop, $barberUser, 'queue-failure-state');

        $this->actingAs($barberUser)
            ->get(route('tenant.google-calendar.callback', ['state' => 'queue-failure-state', 'code' => 'private-code'], false))
            ->assertRedirect(route('tenant.google-calendar.show', $barbershop, false))
            ->assertSessionHas('status');
        $connection = BarberGoogleCalendarConnection::query()->sole();
        $this->assertSame('pending', $connection->backfill_status);

        app()->forgetInstance(GoogleCalendarSync::class);
        Queue::fake([SyncBarberGoogleCalendar::class, SyncAppointmentToGoogleCalendar::class]);
        $reconciler = collect(Schedule::events())->first(fn ($event): bool => $event->getSummaryForDisplay() === 'google-calendar-reconcile');
        $this->assertNotNull($reconciler);
        $reconciler->run(app());
        Queue::assertPushed(SyncBarberGoogleCalendar::class, fn (SyncBarberGoogleCalendar $job): bool => $job->connectionId === $connection->id);
        (new SyncBarberGoogleCalendar($connection->id))->handle(app(GoogleCalendarDispatcher::class));

        $this->assertDatabaseHas('appointment_google_calendar_events', [
            'appointment_id' => $appointment->id,
            'connection_id' => $connection->id,
            'google_calendar_id' => 'calendar-id',
        ]);
        $this->assertSame('complete', $connection->fresh()->backfill_status);
        Queue::assertPushed(SyncAppointmentToGoogleCalendar::class);
    }

    public function test_reconnect_without_a_fresh_refresh_token_does_not_reuse_the_previous_account_token(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-account-access',
                'scope' => 'https://www.googleapis.com/auth/calendar.app.created',
            ]),
        ]);
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $barber = $barbershop->barbers()->where('user_id', $barberUser->getKey())->firstOrFail();
        $connection = $barber->googleCalendarConnection()->create([
            'access_token' => 'previous-account-access',
            'refresh_token' => 'previous-account-refresh',
            'google_calendar_id' => 'previous-calendar-id',
            'connected_at' => now(),
        ]);
        $this->configureCallbackState($barbershop, $barberUser, 'reconnect-state');

        $this->actingAs($barberUser)
            ->get(route('tenant.google-calendar.callback', ['state' => 'reconnect-state', 'code' => 'private-code'], false))
            ->assertRedirect(route('tenant.google-calendar.show', $barbershop, false))
            ->assertSessionHas('calendar_error');

        $connection->refresh();
        $this->assertSame('previous-account-access', $connection->access_token);
        $this->assertSame('previous-account-refresh', $connection->refresh_token);
        $this->assertSame('previous-calendar-id', $connection->google_calendar_id);
    }

    public function test_reconnect_provisions_a_new_calendar_when_the_retained_calendar_is_not_accessible(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-account-access',
                'refresh_token' => 'new-account-refresh',
                'scope' => 'https://www.googleapis.com/auth/calendar.app.created',
            ]),
            'www.googleapis.com/calendar/v3/calendars/old-calendar' => Http::response(['error' => ['code' => 404]], 404),
            'www.googleapis.com/calendar/v3/calendars' => Http::response(['id' => 'new-account-calendar']),
        ]);
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $barber = $barbershop->barbers()->where('user_id', $barberUser->getKey())->firstOrFail();
        $connection = $barber->googleCalendarConnection()->create([
            'access_token' => 'previous-account-access',
            'refresh_token' => 'previous-account-refresh',
            'google_calendar_id' => 'old-calendar',
            'connected_at' => now(),
        ]);
        $this->configureCallbackState($barbershop, $barberUser, 'reconnect-state');

        $this->actingAs($barberUser)
            ->get(route('tenant.google-calendar.callback', ['state' => 'reconnect-state', 'code' => 'private-code'], false))
            ->assertRedirect(route('tenant.google-calendar.show', $barbershop, false))
            ->assertSessionHas('status');

        $connection->refresh();
        $this->assertSame('new-account-calendar', $connection->google_calendar_id);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_ends_with($request->url(), '/calendar/v3/calendars/old-calendar'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://www.googleapis.com/calendar/v3/calendars');
    }

    public function test_reconnect_keeps_a_retained_calendar_only_when_the_new_token_can_access_it(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'same-account-new-access',
                'refresh_token' => 'same-account-new-refresh',
                'scope' => 'https://www.googleapis.com/auth/calendar.app.created',
            ]),
            'www.googleapis.com/calendar/v3/calendars/retained-calendar' => Http::response(['id' => 'retained-calendar']),
        ]);
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $barber = $barbershop->barbers()->where('user_id', $barberUser->getKey())->firstOrFail();
        $connection = $barber->googleCalendarConnection()->create([
            'access_token' => 'old-access',
            'refresh_token' => 'old-refresh',
            'google_calendar_id' => 'retained-calendar',
            'connected_at' => now(),
        ]);
        $this->configureCallbackState($barbershop, $barberUser, 'reconnect-state');

        $this->actingAs($barberUser)
            ->get(route('tenant.google-calendar.callback', ['state' => 'reconnect-state', 'code' => 'private-code'], false))
            ->assertRedirect(route('tenant.google-calendar.show', $barbershop, false))
            ->assertSessionHas('status');

        $connection->refresh();
        $this->assertSame('retained-calendar', $connection->google_calendar_id);
        $this->assertSame('same-account-new-access', $connection->access_token);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_ends_with($request->url(), '/calendar/v3/calendars/retained-calendar'));
    }

    public function test_google_consent_denial_returns_a_safe_message_without_saving_credentials(): void
    {
        Http::preventStrayRequests();
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $this->configureCallbackState($barbershop, $barberUser, 'valid-state');

        $this->actingAs($barberUser)
            ->get(route('tenant.google-calendar.callback', ['state' => 'valid-state', 'error' => 'access_denied'], false))
            ->assertRedirect(route('tenant.google-calendar.show', $barbershop, false))
            ->assertSessionHas('calendar_error');

        $this->assertDatabaseCount('barber_google_calendar_connections', 0);
        Http::assertNothingSent();
    }

    public function test_malformed_token_response_is_handled_without_persisting_or_flashing_provider_content(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response('<private provider body>', 500),
        ]);
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $this->configureCallbackState($barbershop, $barberUser, 'valid-state');

        $response = $this->actingAs($barberUser)
            ->get(route('tenant.google-calendar.callback', ['state' => 'valid-state', 'code' => 'private-code'], false));

        $response->assertRedirect(route('tenant.google-calendar.show', $barbershop, false))
            ->assertSessionHas('calendar_error')
            ->assertDontSee('<private provider body>');
        $this->assertDatabaseCount('barber_google_calendar_connections', 0);
    }

    public function test_successful_scalar_token_response_is_handled_as_invalid_provider_data(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response('42', 200, ['Content-Type' => 'application/json']),
        ]);
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $this->configureCallbackState($barbershop, $barberUser, 'scalar-state');

        $this->actingAs($barberUser)
            ->get(route('tenant.google-calendar.callback', ['state' => 'scalar-state', 'code' => 'private-code'], false))
            ->assertRedirect(route('tenant.google-calendar.show', $barbershop, false))
            ->assertSessionHas('calendar_error');

        $this->assertDatabaseCount('barber_google_calendar_connections', 0);
    }

    public function test_callback_rejects_missing_scope_without_saving_tokens_or_calendar(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'secret-access',
                'refresh_token' => 'secret-refresh',
                'scope' => 'email profile',
            ]),
        ]);
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $this->configureCallbackState($barbershop, $barberUser, 'valid-state');

        $this->actingAs($barberUser)
            ->get(route('tenant.google-calendar.callback', ['state' => 'valid-state', 'code' => 'private-code'], false))
            ->assertRedirect(route('tenant.google-calendar.show', $barbershop, false))
            ->assertSessionHas('calendar_error');

        $this->assertDatabaseCount('barber_google_calendar_connections', 0);
    }

    public function test_calendar_provisioning_failure_does_not_mark_connection_connected(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'secret-access',
                'refresh_token' => 'secret-refresh',
                'scope' => 'https://www.googleapis.com/auth/calendar.app.created',
            ]),
            'www.googleapis.com/calendar/v3/calendars' => Http::response(['error' => ['message' => 'private provider error']], 500),
        ]);
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $this->configureCallbackState($barbershop, $barberUser, 'valid-state');

        $response = $this->actingAs($barberUser)
            ->get(route('tenant.google-calendar.callback', ['state' => 'valid-state', 'code' => 'private-code'], false));

        $response->assertRedirect(route('tenant.google-calendar.show', $barbershop, false))
            ->assertSessionHas('calendar_error');
        $this->assertDatabaseCount('barber_google_calendar_connections', 0);
        $this->assertStringNotContainsString('private provider error', serialize(session()->all()));
    }

    public function test_owner_cannot_connect_another_barbers_calendar_and_must_have_a_linked_self_profile(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = $this->createMember($barbershop, Membership::ROLE_OWNER);
        $otherBarber = $this->createBarber($barbershop);

        $this->actingAs($owner)
            ->post(route('tenant.google-calendar.connect', $barbershop, false), ['barber_id' => Barber::query()->where('user_id', $otherBarber->getKey())->value('id')])
            ->assertNotFound();
    }

    public function test_owner_with_a_linked_self_profile_can_connect_only_their_own_calendar(): void
    {
        config([
            'services.google_calendar.client_id' => 'client-id',
            'services.google_calendar.client_secret' => 'client-secret',
            'services.google_calendar.redirect' => 'https://app.example.test/google-calendar/callback',
        ]);
        $barbershop = Barbershop::factory()->create();
        $owner = $this->createMember($barbershop, Membership::ROLE_OWNER);
        Barber::factory()->for($barbershop)->for($owner, 'user')->create();

        $response = $this->actingAs($owner)->post(route('tenant.google-calendar.connect', $barbershop, false));

        $response->assertRedirect();
        $this->assertSame((int) $owner->getKey(), session('google_calendar_oauth_state.user_id'));
    }

    public function test_oauth_callback_started_before_disconnect_cannot_reconnect_the_calendar(): void
    {
        Http::preventStrayRequests();
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $connection = $barbershop->barbers()->first()->googleCalendarConnection()->create([
            'access_token' => 'secret-access',
            'refresh_token' => 'secret-refresh',
            'google_calendar_id' => 'calendar-id',
            'connected_at' => now(),
        ]);
        $this->configureCallbackState($barbershop, $barberUser, 'stale-state');

        $this->actingAs($barberUser)
            ->delete(route('tenant.google-calendar.disconnect', $barbershop, false))
            ->assertRedirect(route('tenant.google-calendar.show', $barbershop, false));
        $this->get(route('tenant.google-calendar.callback', ['state' => 'stale-state', 'code' => 'private-code'], false))
            ->assertRedirect(route('tenant.google-calendar.show', $barbershop, false))
            ->assertSessionHas('calendar_error');

        $connection->refresh();
        $this->assertNull($connection->connected_at);
        $this->assertSame('calendar-id', $connection->google_calendar_id);
        Http::assertNothingSent();
    }

    public function test_disconnect_clears_credentials_but_keeps_calendar_and_does_not_call_google(): void
    {
        Http::preventStrayRequests();
        $barbershop = Barbershop::factory()->create();
        $barberUser = $this->createBarber($barbershop);
        $connection = $barbershop->barbers()->first()->googleCalendarConnection()->create([
            'access_token' => 'secret-access',
            'refresh_token' => 'secret-refresh',
            'google_calendar_id' => 'calendar-id',
            'connected_at' => now(),
        ]);

        $this->actingAs($barberUser)
            ->delete(route('tenant.google-calendar.disconnect', $barbershop, false))
            ->assertRedirect(route('tenant.google-calendar.show', $barbershop, false));

        $connection->refresh();
        $this->assertNull($connection->access_token);
        $this->assertNull($connection->refresh_token);
        $this->assertNull($connection->connected_at);
        $this->assertSame('calendar-id', $connection->google_calendar_id);
    }

    private function createBarber(Barbershop $barbershop): User
    {
        $user = $this->createMember($barbershop, Membership::ROLE_BARBER);
        Barber::factory()->for($barbershop)->for($user, 'user')->create();

        return $user;
    }

    private function createMember(Barbershop $barbershop, string $role): User
    {
        $user = User::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create(['role' => $role]);

        return $user;
    }

    private function connectionFingerprint(?BarberGoogleCalendarConnection $connection): ?string
    {
        if ($connection === null) {
            return null;
        }

        return hash('sha256', implode("\0", [
            (string) $connection->getRawOriginal('updated_at'),
            (string) $connection->getRawOriginal('connected_at'),
            (string) $connection->google_calendar_id,
            (string) $connection->getRawOriginal('access_token'),
            (string) $connection->getRawOriginal('refresh_token'),
        ]));
    }

    private function configureCallbackState(Barbershop $barbershop, User $user, string $state): void
    {
        config(['services.google_calendar.redirect' => 'https://app.example.test/google-calendar/callback']);
        $barber = $barbershop->barbers()->where('user_id', $user->getKey())->firstOrFail();
        session(['google_calendar_oauth_state' => [
            'state' => $state,
            'user_id' => $user->getKey(),
            'barber_id' => $barber->getKey(),
            'barbershop_id' => $barbershop->getKey(),
            'connection_fingerprint' => $this->connectionFingerprint($barber->googleCalendarConnection),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]]);
    }
}
