<?php

namespace Tests\Feature;

use App\Jobs\SendAppointmentPush;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Membership;
use App\Models\PushSubscription;
use App\Models\Service;
use App\Models\User;
use App\Support\WebPushSender;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\VAPID;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BarberPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_barber_can_subscribe_only_for_their_own_browser_session(): void
    {
        [$barbershop, $barberUser] = $this->barberContext();
        $subscription = $this->subscriptionPayload();

        $this->actingAs($barberUser)
            ->postJson(route('tenant.push-subscriptions.store', $barbershop, false), $subscription)
            ->assertCreated()
            ->assertExactJson(['subscribed' => true]);

        $storedSubscription = PushSubscription::query()->sole();
        $this->assertSame($barberUser->id, $storedSubscription->user_id);
        $this->assertSame(hash('sha256', session()->getId()), $storedSubscription->session_hash);
        $this->assertSame(hash('sha256', $subscription['endpoint']), $storedSubscription->endpoint_hash);
        $this->assertSame($subscription['endpoint'], $storedSubscription->endpoint);
        $this->assertArrayNotHasKey('auth', $storedSubscription->toArray());
        $this->assertArrayNotHasKey('p256dh', $storedSubscription->toArray());
    }

    public function test_subscription_requires_authentication_and_active_barber_membership_and_profile(): void
    {
        [$barbershop] = $this->barberContext();
        $subscription = $this->subscriptionPayload();

        $this->postJson(route('tenant.push-subscriptions.store', $barbershop, false), $subscription)
            ->assertUnauthorized();

        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $this->actingAs($owner)
            ->postJson(route('tenant.push-subscriptions.store', $barbershop, false), $subscription)
            ->assertForbidden();

        [, $inactiveBarber] = $this->barberContext($barbershop, false);
        $this->actingAs($inactiveBarber)
            ->postJson(route('tenant.push-subscriptions.store', $barbershop, false), $subscription)
            ->assertForbidden();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    #[DataProvider('invalidSubscriptions')]
    public function test_malformed_or_unsafe_subscriptions_are_rejected(string $endpoint, string $publicKey, string $auth): void
    {
        [$barbershop, $barberUser] = $this->barberContext();

        $this->actingAs($barberUser)
            ->postJson(route('tenant.push-subscriptions.store', $barbershop, false), [
                'endpoint' => $endpoint,
                'keys' => ['p256dh' => $publicKey, 'auth' => $auth],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public static function invalidSubscriptions(): array
    {
        return [
            'unknown host' => ['https://attacker.example/push', self::validPublicKey(), self::validAuthKey()],
            'localhost' => ['https://localhost/push', self::validPublicKey(), self::validAuthKey()],
            'private IP' => ['https://127.0.0.1/push', self::validPublicKey(), self::validAuthKey()],
            'non-443 port' => ['https://fcm.googleapis.com:8443/send/token', self::validPublicKey(), self::validAuthKey()],
            'embedded credentials' => ['https://user:pass@fcm.googleapis.com/send/token', self::validPublicKey(), self::validAuthKey()],
            'fragment' => ['https://fcm.googleapis.com/send/token#fragment', self::validPublicKey(), self::validAuthKey()],
            'malformed public key' => ['https://fcm.googleapis.com/send/token', 'bad', self::validAuthKey()],
            'invalid curve point' => ['https://fcm.googleapis.com/send/token', self::encode("\x04".str_repeat('p', 64)), self::validAuthKey()],
            'malformed auth key' => ['https://fcm.googleapis.com/send/token', self::validPublicKey(), 'bad'],
        ];
    }

    public function test_other_users_cannot_claim_or_delete_an_existing_endpoint(): void
    {
        [$barbershop, $firstBarber] = $this->barberContext();
        [, $secondBarber] = $this->barberContext($barbershop);
        $payload = $this->subscriptionPayload();

        $this->actingAs($firstBarber)
            ->postJson(route('tenant.push-subscriptions.store', $barbershop, false), $payload)
            ->assertCreated();
        $subscription = PushSubscription::query()->sole();

        $this->actingAs($secondBarber)
            ->postJson(route('tenant.push-subscriptions.store', $barbershop, false), $payload)
            ->assertConflict();
        $this->actingAs($secondBarber)
            ->deleteJson(route('tenant.push-subscriptions.destroy', [$barbershop, $subscription->id], false))
            ->assertNotFound();

        $this->assertDatabaseHas('push_subscriptions', ['id' => $subscription->id, 'user_id' => $firstBarber->id]);
    }

    public function test_barber_cannot_unsubscribe_a_subscription_from_another_browser_session(): void
    {
        [$barbershop, $barberUser] = $this->barberContext();
        $subscription = PushSubscription::factory()->for($barberUser)->create([
            'session_hash' => hash('sha256', 'another-device'),
        ]);

        $this->actingAs($barberUser)
            ->deleteJson(route('tenant.push-subscriptions.destroy', [$barbershop, $subscription->id], false))
            ->assertNotFound();

        $this->assertModelExists($subscription);
    }

    public function test_same_browser_can_refresh_and_delete_its_subscription_without_affecting_other_devices(): void
    {
        [$barbershop, $barberUser] = $this->barberContext();
        $payload = $this->subscriptionPayload();
        $this->withCredentials();

        $subscribeResponse = $this->actingAs($barberUser)
            ->postJson(route('tenant.push-subscriptions.store', $barbershop, false), $payload);
        $subscribeResponse->assertCreated();
        $this->withCookie(config('session.cookie'), $subscribeResponse->getCookie(config('session.cookie'))?->getValue());

        $otherDevice = PushSubscription::factory()->for($barberUser)->create([
            'session_hash' => hash('sha256', 'another-device'),
        ]);
        $refreshPayload = $this->subscriptionPayload();

        $this->postJson(route('tenant.push-subscriptions.store', $barbershop, false), $refreshPayload)
            ->assertOk()
            ->assertExactJson(['subscribed' => true]);

        $this->assertDatabaseCount('push_subscriptions', 2);
        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint_hash' => hash('sha256', $refreshPayload['endpoint']),
            'p256dh' => $refreshPayload['keys']['p256dh'],
        ]);

        $subscription = PushSubscription::query()->where('endpoint_hash', hash('sha256', $payload['endpoint']))->sole();
        $this->deleteJson(route('tenant.push-subscriptions.destroy', [$barbershop, $subscription->id], false))
            ->assertOk()
            ->assertExactJson(['subscribed' => false]);

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertModelExists($otherDevice);
    }

    public function test_logout_removes_only_subscriptions_for_the_current_browser_session(): void
    {
        [$barbershop, $user] = $this->barberContext();
        $subscribeResponse = $this->actingAs($user)
            ->postJson(route('tenant.push-subscriptions.store', $barbershop, false), $this->subscriptionPayload());
        $subscribeResponse->assertCreated();
        $this->withCookie(config('session.cookie'), $subscribeResponse->getCookie(config('session.cookie'))?->getValue());
        PushSubscription::factory()->for($user)->create(['session_hash' => hash('sha256', 'another-device')]);

        $this->post('/logout')->assertRedirect('/');

        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->id, 'session_hash' => hash('sha256', 'another-device')]);
    }

    public function test_owner_scheduling_for_another_barber_queues_assigned_event_after_success(): void
    {
        [$barbershop, $owner, $barber, $service] = $this->appointmentContext();
        Queue::fake();
        config(['webpush.public_key' => 'test-public-key', 'webpush.private_key' => 'test-private-key', 'webpush.subject' => 'mailto:test@example.test']);

        $this->actingAs($owner)
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'customer_id' => '',
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect();

        Queue::assertPushed(SendAppointmentPush::class, fn (SendAppointmentPush $job): bool => $job->event === 'assigned' && $job->actorUserId === $owner->id
        );
    }

    public function test_owner_rescheduling_and_cancellation_queue_only_their_matching_events(): void
    {
        [$barbershop, $owner, , , $appointment] = $this->appointmentContext();
        Queue::fake();
        config(['webpush.public_key' => 'test-public-key', 'webpush.private_key' => 'test-private-key', 'webpush.subject' => 'mailto:test@example.test']);

        $this->actingAs($owner)
            ->put(route('tenant.appointments.reschedule', [$barbershop, $appointment], false), ['starts_at' => '2026-10-05T11:00'])
            ->assertRedirect();

        Queue::assertPushed(SendAppointmentPush::class, fn (SendAppointmentPush $job): bool => $job->event === 'rescheduled');

        Queue::fake();
        $this->post(route('tenant.appointments.cancel', [$barbershop, $appointment], false))->assertRedirect();

        Queue::assertPushed(SendAppointmentPush::class, fn (SendAppointmentPush $job): bool => $job->event === 'cancelled');
    }

    public function test_own_actions_and_completed_or_no_show_transitions_do_not_queue_push(): void
    {
        [$barbershop, , $barber, $service, $appointment] = $this->appointmentContext();
        $barberUser = $barber->user;
        Queue::fake();
        config(['webpush.public_key' => 'test-public-key', 'webpush.private_key' => 'test-private-key', 'webpush.subject' => 'mailto:test@example.test']);

        $this->actingAs($barberUser)
            ->put(route('tenant.appointments.reschedule', [$barbershop, $appointment], false), ['starts_at' => '2026-10-05T11:00'])
            ->assertRedirect();

        $this->post(route('tenant.appointments.complete', [$barbershop, $appointment], false))->assertRedirect();
        Queue::assertNothingPushed();

        $cancelledAppointment = Appointment::factory()->for($barbershop)->for($appointment->barber)->for($service)->create([
            'starts_at' => '2026-10-05 12:00:00', 'ends_at' => '2026-10-05 12:30:00',
        ]);
        $this->post(route('tenant.appointments.cancel', [$barbershop, $cancelledAppointment], false))->assertRedirect();
        Queue::assertNothingPushed();

        $noShowAppointment = Appointment::factory()->for($barbershop)->for($appointment->barber)->for($service)->create([
            'starts_at' => '2026-10-05 13:00:00', 'ends_at' => '2026-10-05 13:30:00',
        ]);
        $this->post(route('tenant.appointments.no-show', [$barbershop, $noShowAppointment], false))->assertRedirect();
        Queue::assertNothingPushed();
    }

    public function test_rejected_schedule_does_not_queue_assigned_event(): void
    {
        [$barbershop, $owner, $barber, $service, $appointment] = $this->appointmentContext();
        Queue::fake();
        config(['webpush.public_key' => 'test-public-key', 'webpush.private_key' => 'test-private-key', 'webpush.subject' => 'mailto:test@example.test']);

        $this->actingAs($owner)
            ->from(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'customer_id' => '',
                'starts_at' => $appointment->starts_at->format('Y-m-d\\TH:i'),
            ])
            ->assertSessionHasErrors('starts_at');

        Queue::assertNothingPushed();
    }

    public function test_job_rechecks_active_barber_membership_before_using_sender(): void
    {
        [$barbershop, $barberUser, $barber, $service, $appointment] = $this->appointmentContext();
        $sender = $this->mock(WebPushSender::class);
        $sender->shouldNotReceive('send');
        Membership::query()->where('user_id', $barberUser->id)->update(['status' => Membership::STATUS_INACTIVE]);

        (new SendAppointmentPush($appointment->id, $barberUser->id, 999999, 'assigned'))->handle($sender);

        Membership::query()->where('user_id', $barberUser->id)->update(['status' => Membership::STATUS_ACTIVE]);
        $barber->update(['active' => false]);
        (new SendAppointmentPush($appointment->id, $barberUser->id, 999999, 'assigned'))->handle($sender);

        $this->assertSame($barbershop->id, $appointment->barbershop_id);
        $this->assertSame($barber->id, $appointment->barber_id);
        $this->assertSame($service->id, $appointment->service_id);
    }

    public function test_job_sends_generic_payload_to_the_assigned_barbers_subscriptions(): void
    {
        [$barbershop, $owner, $barber, , $appointment] = $this->appointmentContext();
        $subscription = PushSubscription::factory()->for($barber->user)->create();
        $sender = $this->mock(WebPushSender::class);
        $sender->shouldReceive('send')
            ->once()
            ->withArgs(function (PushSubscription $sentSubscription, array $payload) use ($subscription, $barbershop): bool {
                $this->assertSame($subscription->id, $sentSubscription->id);
                $this->assertSame('Nueva cita asignada', $payload['title']);
                $this->assertSame('Tienes una cita nueva en tu agenda.', $payload['body']);
                $this->assertSame(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false), $payload['url']);
                $this->assertArrayNotHasKey('customer', $payload);

                return true;
            });

        (new SendAppointmentPush($appointment->id, $barber->user_id, $owner->id, 'assigned'))->handle($sender);
    }

    #[DataProvider('expiredPushStatuses')]
    public function test_sender_removes_expired_subscription_without_live_http(int $statusCode): void
    {
        $subscription = PushSubscription::factory()->create(['p256dh' => self::validPublicKey()]);
        $keys = VAPID::createVapidKeys();
        config(['webpush.public_key' => $keys['publicKey'], 'webpush.private_key' => $keys['privateKey'], 'webpush.subject' => 'mailto:test@example.test']);
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response($statusCode)]))]);

        (new WebPushSender($client))->send($subscription, [
            'title' => 'Cita cancelada',
            'body' => 'Una cita de tu agenda fue cancelada.',
            'url' => '/barbershops/centro/appointments?date=2026-10-05',
        ]);

        $this->assertModelMissing($subscription);
    }

    public static function expiredPushStatuses(): array
    {
        return ['not found' => [404], 'gone' => [410]];
    }

    public function test_sender_does_not_follow_push_service_redirects(): void
    {
        $subscription = PushSubscription::factory()->create(['p256dh' => self::validPublicKey()]);
        $keys = VAPID::createVapidKeys();
        config(['webpush.public_key' => $keys['publicKey'], 'webpush.private_key' => $keys['privateKey'], 'webpush.subject' => 'mailto:test@example.test']);
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(307, ['Location' => 'https://attacker.example/collect']),
            new Response(201),
        ]));
        $stack->push(Middleware::history($history));

        (new WebPushSender(new Client(['handler' => $stack])))->send($subscription, [
            'title' => 'Cita cancelada',
            'body' => 'Una cita de tu agenda fue cancelada.',
            'url' => '/barbershops/centro/appointments?date=2026-10-05',
        ]);

        $this->assertCount(1, $history);
        $this->assertSame('fcm.googleapis.com', $history[0]['request']->getUri()->getHost());
        $this->assertModelExists($subscription);
    }

    /** @return array{0: Barbershop, 1: User, 2: Barber, 3: Service, 4: Appointment} */
    private function appointmentContext(): array
    {
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        [$barbershop, $barberUser] = $this->barberContext($barbershop);
        $barber = Barber::query()->where('user_id', $barberUser->id)->firstOrFail();
        $service = Service::factory()->for($barbershop)->create(['active' => true, 'duration_minutes' => 30]);
        BusinessHour::factory()->for($barbershop)->create(['weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00', 'closed' => false]);
        BarberAvailability::factory()->for($barber)->create(['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '18:00', 'active' => true]);
        $appointment = Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'starts_at' => '2026-10-05 10:00:00', 'ends_at' => '2026-10-05 10:30:00',
        ]);

        return [$barbershop, $owner, $barber, $service, $appointment];
    }

    private function barberContext(?Barbershop $barbershop = null, bool $active = true): array
    {
        $barbershop ??= Barbershop::factory()->create();
        $barberUser = User::factory()->create();
        Membership::factory()->for($barberUser)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        Barber::factory()->for($barbershop)->create(['user_id' => $barberUser->id, 'active' => $active]);

        return [$barbershop, $barberUser];
    }

    private function subscriptionPayload(): array
    {
        return [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-token',
            'keys' => ['p256dh' => self::validPublicKey(), 'auth' => self::validAuthKey()],
        ];
    }

    private static function validPublicKey(): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $details = openssl_pkey_get_details($key);

        return self::encode("\x04".str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT));
    }

    private static function validAuthKey(): string
    {
        return self::encode(str_repeat('a', 16));
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
