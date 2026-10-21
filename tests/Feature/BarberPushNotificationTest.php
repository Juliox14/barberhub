<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
