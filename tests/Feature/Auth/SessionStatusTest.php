<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\SingleDeviceSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
    }

    public function test_status_returns_current_without_touching_account_activity(): void
    {
        $user = User::factory()->create();
        $ownerToken = Str::random(64);
        $activity = now()->subMinutes(5);
        $user->forceFill([
            'active_session_id' => $ownerToken,
            'active_session_at' => $activity,
        ])->save();

        $response = $this->actingAs($user)->withSession([
            SingleDeviceSession::OWNER_TOKEN_KEY => $ownerToken,
        ])->getJson(route('session.status'));

        $response->assertOk()
            ->assertExactJson(['status' => 'current'])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'active_session_id' => $ownerToken,
            'active_session_at' => $activity->toDateTimeString(),
        ]);
        $this->assertSame(['status'], array_keys($response->json()));
    }

    public function test_guest_status_returns_noncacheable_unauthorized_json(): void
    {
        $response = $this->getJson(route('session.status'));

        $response->assertUnauthorized()
            ->assertExactJson(['status' => 'expired'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_revoked_owner_returns_noncacheable_unauthorized_json(): void
    {
        $user = User::factory()->create([
            'active_session_id' => Str::random(64),
            'active_session_at' => now(),
        ]);

        $response = $this->actingAs($user)->withSession([
            SingleDeviceSession::OWNER_TOKEN_KEY => Str::random(64),
        ])->getJson(route('session.status'));

        $response->assertUnauthorized()
            ->assertExactJson(['status' => 'revoked'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->get(route('login', ['session' => 'revoked']))
            ->assertOk()
            ->assertSee('Tu sesión se cerró porque iniciaste sesión en otro dispositivo.');
    }

    public function test_idle_owner_returns_expired_without_refreshing_activity(): void
    {
        $user = User::factory()->create();
        $ownerToken = Str::random(64);
        $activity = now()->subMinutes(config('session.lifetime') + 1);
        $user->forceFill([
            'active_session_id' => $ownerToken,
            'active_session_at' => $activity,
        ])->save();

        $response = $this->actingAs($user)->withSession([
            SingleDeviceSession::OWNER_TOKEN_KEY => $ownerToken,
        ])->getJson(route('session.status'));

        $response->assertUnauthorized()
            ->assertExactJson(['status' => 'expired'])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'active_session_at' => $activity->toDateTimeString(),
        ]);
    }

    public function test_missing_owner_does_not_claim_legacy_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson(route('session.status'));

        $response->assertUnauthorized()->assertExactJson(['status' => 'expired']);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'active_session_id' => null,
            'active_session_at' => null,
        ]);
    }
}
