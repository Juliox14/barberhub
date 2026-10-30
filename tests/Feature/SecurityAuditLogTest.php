<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_is_recorded_without_credentials(): void
    {
        $user = User::factory()->create();
        $password = 'a-never-log-this-secret';

        $userAgent = str_repeat('Safe browser identifier ', 40);
        $response = $this->withHeader('User-Agent', $userAgent)->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'login.failed',
            'actor_id' => null,
            'subject_id' => null,
        ]);
        $logs = json_encode($this->auditRows(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($password, $logs);
        $this->assertStringNotContainsString($user->email, $logs);
        $auditRecord = DB::table('security_audit_logs')->first();
        $this->assertSame(500, mb_strlen($auditRecord->user_agent));
        $this->assertLessThanOrEqual(45, mb_strlen($auditRecord->ip_address));
    }

    public function test_successful_login_and_logout_are_recorded_once(): void
    {
        $user = User::factory()->create();
        $barbershop = Barbershop::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $response->assertRedirect(route('tenant.dashboard', $barbershop, false));
        $this->assertAuthenticatedAs($user);
        $this->get('/profile')->assertOk();
        $this->post('/logout')->assertRedirect('/');

        $this->assertDatabaseCount('security_audit_logs', 2);
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'login.succeeded', 'actor_id' => $user->id, 'subject_id' => $user->id]);
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'logout', 'actor_id' => $user->id, 'subject_id' => $user->id]);
    }

    public function test_event_specific_allow_list_rejects_unexpected_secret_metadata(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(\App\Support\SecurityAuditLogger::class)->record(
            'login.failed',
            metadata: ['password' => 'must-not-be-stored'],
        );
    }

    public function test_throttled_login_is_recorded_without_email_or_password(): void
    {
        $password = 'another-never-log-this-secret';

        foreach (range(1, 5) as $attempt) {
            $this->from('/login')->post('/login', ['email' => 'missing@example.com', 'password' => $password]);
        }

        $response = $this->from('/login')->post('/login', ['email' => 'missing@example.com', 'password' => $password]);

        $response->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'login.locked_out', 'actor_id' => null]);
        $logs = json_encode($this->auditRows(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($password, $logs);
        $this->assertStringNotContainsString('missing@example.com', $logs);
    }

    public function test_confirmed_session_transfer_is_recorded_without_owner_tokens(): void
    {
        config(['session.driver' => 'database']);
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        $user = User::factory()->create();
        $barbershop = Barbershop::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create();

        $originalLogin = $this->freshBrowser()->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $originalLogin->assertRedirect();
        $conflict = $this->freshBrowser()->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $conflict->assertOk()->assertSee('Ya hay una sesión abierta');
        $pendingSessionId = $this->sessionIdFrom($conflict);

        $transfer = $this->browser($pendingSessionId)->post('/login/session-conflict/confirm');
        $transfer->assertRedirect();
        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'session.transfer_confirmed',
            'actor_id' => $user->id,
            'subject_id' => $user->id,
            'metadata' => '[]',
        ]);
        $this->assertSame(2, DB::table('security_audit_logs')->where('event', 'login.succeeded')->count());
    }

    public function test_password_update_and_reset_are_recorded_after_success(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => 'replacement-secret',
            'password_confirmation' => 'replacement-secret',
        ])->assertRedirect('/profile');
        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'password.updated',
            'actor_id' => $user->id,
            'subject_id' => $user->id,
        ]);
        $this->post('/logout');

        $this->post('/forgot-password', ['email' => $user->email]);
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'reset-secret',
                'password_confirmation' => 'reset-secret',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'password.reset',
            'actor_id' => null,
            'subject_id' => $user->id,
        ]);
        $logs = json_encode($this->auditRows(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('replacement-secret', $logs);
        $this->assertStringNotContainsString('reset-secret', $logs);
    }

    public function test_platform_barbershop_mutations_record_successful_subject_identity(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $barbershop = Barbershop::factory()->create(['slug' => 'old-shop']);

        $this->actingAs($admin)->post(route('platform.barbershops.store', absolute: false), [
            'name' => 'Created Shop',
            'slug' => 'created-shop',
            'timezone' => 'America/Bogota',
            'owner_email' => $owner->email,
        ])->assertRedirect(route('platform.barbershops.index', absolute: false));
        $created = Barbershop::query()->where('slug', 'created-shop')->firstOrFail();

        $this->put(route('platform.barbershops.update', $barbershop, false), [
            'name' => 'Updated Shop',
            'slug' => 'old-shop',
            'timezone' => 'America/Bogota',
            'status' => Barbershop::STATUS_INACTIVE,
        ])->assertRedirect(route('platform.barbershops.index', absolute: false));
        $this->delete(route('platform.barbershops.destroy', $barbershop, false))
            ->assertRedirect(route('platform.barbershops.index', absolute: false));

        $this->assertDatabaseHas('security_audit_logs', ['event' => 'platform.barbershop.created', 'actor_id' => $admin->id, 'subject_id' => $created->id]);
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'platform.barbershop.updated', 'actor_id' => $admin->id, 'subject_id' => $barbershop->id]);
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'platform.barbershop.deleted', 'actor_id' => $admin->id, 'subject_id' => $barbershop->id]);
        $this->assertDatabaseCount('security_audit_logs', 3);
    }

    public function test_tenant_membership_mutations_record_subject_and_reject_failed_operations(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $barbershop = Barbershop::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $membership = Membership::factory()->for($member)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);

        $this->actingAs($owner)->put(route('tenant.members.update', [$barbershop, $membership], false), [
            'role' => Membership::ROLE_ADMIN,
            'status' => Membership::STATUS_ACTIVE,
        ])->assertRedirect(route('tenant.members.index', $barbershop, false));
        $this->delete(route('tenant.members.destroy', [$barbershop, $membership], false))
            ->assertRedirect(route('tenant.members.index', $barbershop, false));
        $this->post(route('tenant.members.store', $barbershop, false), [
            'email' => $member->email,
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ])->assertRedirect(route('tenant.members.index', $barbershop, false));

        $this->assertDatabaseHas('security_audit_logs', ['event' => 'tenant.membership.updated', 'actor_id' => $owner->id, 'subject_id' => $member->id, 'barbershop_id' => $barbershop->id]);
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'tenant.membership.deleted', 'actor_id' => $owner->id, 'subject_id' => $member->id, 'barbershop_id' => $barbershop->id]);
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'tenant.membership.created', 'actor_id' => $owner->id, 'subject_id' => $member->id, 'barbershop_id' => $barbershop->id]);
        $this->assertDatabaseCount('security_audit_logs', 3);
    }

    private function browser(string $sessionId): self
    {
        $this->resetBrowserSessionBindings();

        return $this->withCookie(config('session.cookie'), $sessionId);
    }

    private function freshBrowser(): self
    {
        $this->resetBrowserSessionBindings();

        return $this->withCookie(config('session.cookie'), Str::random(40));
    }

    private function sessionIdFrom(TestResponse $response): string
    {
        return $response->getCookie(config('session.cookie'))->getValue();
    }

    private function resetBrowserSessionBindings(): void
    {
        Auth::forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
    }

    /** @return array<int, object> */
    private function auditRows(): array
    {
        return DB::table('security_audit_logs')->get()->all();
    }
}
