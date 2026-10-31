<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Support\SingleDeviceSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    public function test_password_alone_cannot_claim_or_transfer_an_enabled_account_session(): void
    {
        $user = $this->account(enabled: true);
        $user->forceFill(['active_session_id' => 'legitimate-owner', 'active_session_at' => now()])->save();
        $before = $user->fresh()->getRawOriginal();

        $this->post('/login', $this->credentials($user))->assertRedirect('/two-factor/challenge');
        $this->assertGuest();
        $this->assertSame('legitimate-owner', $user->fresh()->active_session_id);
        $this->post('/login/session-conflict/confirm')->assertStatus(419);
        $this->assertSame($before['remember_token'], $user->fresh()->getRawOriginal('remember_token'));
        $this->assertDatabaseMissing('security_audit_logs', ['subject_id' => $user->id, 'event' => 'login.succeeded']);
    }

    public function test_valid_factor_completes_login_and_preserves_the_original_dashboard(): void
    {
        $user = $this->account(enabled: true);
        $this->post('/login', $this->credentials($user))->assertRedirect('/two-factor/challenge');
        $this->get('/two-factor/challenge')->assertSee('Código de autenticación');

        $this->post('/two-factor/challenge', ['code' => $this->code()])->assertRedirect('/platform/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get('/platform/dashboard')->assertOk();
        $this->assertDatabaseHas('security_audit_logs', ['subject_id' => $user->id, 'event' => 'login.succeeded']);
    }

    public function test_invalid_factor_changes_no_ownership_or_recovery_state_and_is_account_rate_limited(): void
    {
        $user = $this->account(enabled: true);
        $user->forceFill(['two_factor_recovery_codes' => [Hash::make('RECOVERY-CODE')]])->save();
        $this->post('/login', $this->credentials($user));
        $before = $user->fresh()->getRawOriginal();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->from('/two-factor/challenge')->post('/two-factor/challenge', ['code' => 'bad'])
                ->assertSessionHasErrors('code');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.22'])
            ->post('/two-factor/challenge', ['code' => $this->code()])->assertStatus(429);
        $this->assertGuest();
        $this->assertSame($before, $user->fresh()->getRawOriginal());
    }

    public function test_recovery_code_logs_in_once_but_cannot_be_reused(): void
    {
        $user = $this->account(enabled: true);
        $user->forceFill(['two_factor_recovery_codes' => [Hash::make('RECOVERY-CODE')]])->save();
        $this->post('/login', $this->credentials($user));
        $this->post('/two-factor/challenge', ['recovery_code' => 'RECOVERY-CODE'])->assertRedirect('/platform/dashboard');
        $this->post('/logout');
        $this->post('/login', $this->credentials($user));

        $this->post('/two-factor/challenge', ['recovery_code' => 'RECOVERY-CODE'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_pending_challenge_expires_and_password_or_revocation_changes_invalidate_it(): void
    {
        $user = $this->account(enabled: true);
        $this->post('/login', $this->credentials($user));
        $this->travel(10)->minutes();
        $this->post('/two-factor/challenge', ['code' => $this->code()])->assertStatus(419);
        $this->travelBack();
        $this->post('/login', $this->credentials($user));
        $user->forceFill(['security_session_version' => 1])->save();
        $this->post('/two-factor/challenge', ['code' => $this->code()])->assertStatus(419);
        $this->assertGuest();
        $this->post('/login', $this->credentials($user));
        $user->forceFill(['password' => 'Replacement123!'])->save();
        $this->post('/two-factor/challenge', ['code' => $this->code()])->assertStatus(419);
        $this->assertGuest();
    }

    public function test_active_session_conflict_requires_factor_before_confirmation(): void
    {
        $user = $this->account(enabled: true);
        $user->forceFill(['active_session_id' => 'other-browser', 'active_session_at' => now()])->save();
        $this->post('/login', $this->credentials($user));
        $this->post('/two-factor/challenge', ['code' => $this->code()])->assertSee('Ya hay una sesión abierta');
        $this->assertGuest();
        $this->assertSame('other-browser', $user->fresh()->active_session_id);

        $this->post('/login/session-conflict/confirm')->assertRedirect('/platform/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame('other-browser', $user->fresh()->active_session_id);
    }

    public function test_existing_session_without_factor_proof_is_restricted_and_can_verify(): void
    {
        $user = $this->account(enabled: true);
        $this->actingAs($user)->get('/platform/dashboard')->assertRedirect('/two-factor/challenge');
        $this->post('/two-factor/challenge', ['code' => $this->code()])->assertRedirect('/platform/dashboard');
        $this->get('/platform/dashboard')->assertOk();
    }

    public function test_mandatory_grace_has_exact_fourteen_day_boundary_and_optional_roles_remain_optional(): void
    {
        $this->freezeTime();
        $user = $this->account();
        $user->forceFill(['two_factor_enrollment_started_at' => now()->subDays(14)->addSecond()])->save();
        $this->actingAs($user)->get('/platform/dashboard')->assertSee('Configura la autenticación en dos pasos');
        $this->travel(1)->seconds();
        $this->get('/platform/dashboard')->assertRedirect('/two-factor/settings');
        $this->get('/two-factor/settings')->assertOk();
        $this->post('/logout')->assertRedirect('/');
        $client = User::factory()->create(['two_factor_enrollment_started_at' => now()->subYears(1)]);
        $this->actingAs($client)->get('/profile')->assertOk();
    }

    public function test_active_owner_and_admin_memberships_require_enrollment_but_inactive_membership_does_not(): void
    {
        foreach ([Membership::ROLE_OWNER, Membership::ROLE_ADMIN] as $role) {
            $user = User::factory()->create(['two_factor_enrollment_started_at' => now()->subDays(14)]);
            Membership::factory()->for($user)->for(Barbershop::factory())->create(['role' => $role, 'status' => Membership::STATUS_ACTIVE]);
            $this->actingAs($user)->get('/profile')->assertRedirect('/two-factor/settings');
        }
        $inactive = User::factory()->create(['two_factor_enrollment_started_at' => now()->subDays(14)]);
        Membership::factory()->for($inactive)->for(Barbershop::factory())->create(['role' => Membership::ROLE_OWNER, 'status' => Membership::STATUS_INACTIVE]);
        $this->actingAs($inactive)->get('/profile')->assertOk();
    }

    public function test_expired_password_has_a_renewal_path_even_after_mandatory_grace(): void
    {
        $user = $this->account();
        $user->forceFill(['password_changed_at' => now()->subDays(90), 'two_factor_enrollment_started_at' => now()->subDays(14)])->save();
        $this->actingAs($user)->get('/platform/dashboard')->assertRedirect(route('password.renew'));
        $this->get(route('password.renew'))->assertOk();
        $this->put('/password', ['current_password' => 'password', 'password' => 'Replacement123!', 'password_confirmation' => 'Replacement123!'])->assertSessionHasNoErrors();
        $this->get('/platform/dashboard')->assertRedirect('/two-factor/settings');
    }

    public function test_session_poll_reports_two_factor_requirement_without_business_data(): void
    {
        $user = $this->account();
        $user->forceFill(['two_factor_enrollment_started_at' => now()->subDays(14)])->save();
        app(SingleDeviceSession::class)->claimAfterCredentials($user, 'current-owner');
        $this->actingAs($user)->withSession([
            Auth::guard('web')->getName() => $user->id,
            SingleDeviceSession::OWNER_TOKEN_KEY => 'current-owner',
        ]);
        $this->getJson('/session/status')->assertStatus(403)->assertExactJson([
            'status' => 'two_factor_required', 'redirect' => route('two-factor.settings'),
        ])->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_a_remember_cookie_does_not_bypass_a_factor_enabled_after_the_original_login(): void
    {
        $user = $this->account();
        $login = $this->post('/login', [...$this->credentials($user), 'remember' => 'on']);
        $rememberName = Auth::guard('web')->getRecallerName();
        $remember = $login->getCookie($rememberName)->getValue();
        $user->forceFill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now(), 'two_factor_version' => 1])->save();
        Auth::forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');

        $this->withCookie(config('session.cookie'), Str::random(40))->withCookie($rememberName, $remember)
            ->get('/platform/dashboard')->assertRedirect('/two-factor/challenge');
        $this->post('/two-factor/challenge', ['code' => $this->code()])->assertRedirect('/platform/dashboard');
        $this->get('/platform/dashboard')->assertOk();
    }

    public function test_factor_state_changes_invalidate_pending_login_and_transfer_proof(): void
    {
        $user = $this->account(enabled: true);
        $this->post('/login', $this->credentials($user));
        $user->forceFill(['two_factor_version' => 2])->save();
        $this->post('/two-factor/challenge', ['code' => $this->code()])->assertStatus(419);
        $user->forceFill(['active_session_id' => 'legitimate-owner', 'active_session_at' => now()])->save();
        $this->post('/login', $this->credentials($user));
        $this->post('/two-factor/challenge', ['code' => $this->code()])->assertOk();
        $user->refresh()->forceFill(['two_factor_version' => 4])->save();

        $this->post('/login/session-conflict/confirm')->assertStatus(419);
        $this->assertGuest();
        $this->assertSame('legitimate-owner', $user->fresh()->active_session_id);
    }

    public function test_new_malformed_login_discards_the_previous_pending_factor(): void
    {
        $user = $this->account(enabled: true);
        $this->post('/login', $this->credentials($user));
        $this->post('/login', ['email' => $user->email])->assertSessionHasErrors('password');

        $this->post('/two-factor/challenge', ['code' => $this->code()])->assertStatus(419);
        $this->assertGuest();
    }

    public function test_expired_password_and_enabled_factor_do_not_create_a_redirect_loop(): void
    {
        $user = $this->account(enabled: true);
        $user->forceFill(['password_changed_at' => now()->subDays(90)])->save();
        $this->actingAs($user)->get('/platform/dashboard')->assertRedirect('/two-factor/challenge');
        $this->get('/two-factor/challenge')->assertOk();
        $this->post('/two-factor/challenge', ['code' => $this->code()])->assertRedirect('/platform/dashboard');
        $this->get('/platform/dashboard')->assertRedirect(route('password.renew'));
        $this->get(route('password.renew'))->assertOk();
    }

    public function test_enrollment_confirmation_keeps_the_current_browser_verified(): void
    {
        $user = $this->account();
        $this->actingAs($user)->post('/two-factor/begin', ['current_password' => 'password'])->assertOk();
        $secret = $user->fresh()->two_factor_pending_secret;
        $this->post('/two-factor/confirm', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
            ->assertOk()->assertSee('Guarda tus códigos de recuperación');

        $this->get('/platform/dashboard')->assertOk();
        $this->get('/two-factor/settings')->assertSee('name="recovery_code"', false);
    }

    public function test_factor_password_confirmation_is_rate_limited_and_secrets_are_never_flashed(): void
    {
        $user = $this->account();
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($user)->post('/two-factor/begin', [
                'current_password' => 'wrong', 'code' => '123456', 'recovery_code' => 'private-code',
            ])->assertSessionHasErrors('current_password')->assertSessionMissing('_old_input');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.55'])
            ->post('/two-factor/begin', ['current_password' => 'password'])->assertStatus(429);
        $this->assertNull($user->fresh()->two_factor_pending_secret);
    }

    public function test_enrollment_confirmation_is_bound_to_recent_password_proof_and_the_same_browser(): void
    {
        $user = $this->account();
        $this->actingAs($user)->post('/two-factor/begin', ['current_password' => 'password'])->assertOk();
        $secret = $user->fresh()->two_factor_pending_secret;
        $user->forceFill(['password' => 'Replacement123!'])->save();
        $this->post('/two-factor/confirm', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertStatus(419);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->post('/two-factor/begin', ['current_password' => 'Replacement123!'])->assertOk();
        $secret = $user->fresh()->two_factor_pending_secret;
        $this->travel(10)->minutes();
        $this->post('/two-factor/confirm', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertStatus(419);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->travelBack();
        $this->post('/two-factor/begin', ['current_password' => 'Replacement123!'])->assertOk();
        $secret = $user->fresh()->two_factor_pending_secret;
        $this->withCookie(config('session.cookie'), Str::random(40))
            ->post('/two-factor/confirm', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertStatus(419);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_session_poll_reports_revocation_before_any_factor_restriction(): void
    {
        $user = $this->account(enabled: true);
        $user->forceFill(['active_session_id' => 'new-owner', 'active_session_at' => now()])->save();
        $this->actingAs($user)->withSession([
            Auth::guard('web')->getName() => $user->id,
            SingleDeviceSession::OWNER_TOKEN_KEY => 'old-owner',
        ])->getJson('/session/status')->assertStatus(401)->assertExactJson(['status' => 'revoked']);
        $this->assertGuest();
    }

    public function test_stale_authenticated_identity_cannot_enroll_after_security_revocation(): void
    {
        $user = $this->account();
        $this->actingAs($user);
        User::query()->whereKey($user->id)->update(['security_session_version' => 1]);

        $this->post('/two-factor/begin', ['current_password' => 'password'])->assertStatus(419);
        $this->assertNull($user->fresh()->two_factor_pending_secret);
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        $cookie = $response->getCookie(config('session.cookie'));
        if ($cookie !== null) {
            $this->withCookie(config('session.cookie'), $cookie->getValue());
        }

        return $response;
    }

    private function account(bool $enabled = false): User
    {
        return User::factory()->create([
            'is_platform_admin' => true,
            ...($enabled ? ['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now(), 'two_factor_last_used_step' => null] : []),
        ]);
    }

    private function code(): string
    {
        return app(Google2FA::class)->getCurrentOtp(self::SECRET);
    }

    /** @return array{email: string, password: string} */
    private function credentials(User $user): array
    {
        return ['email' => $user->email, 'password' => 'password'];
    }
}
