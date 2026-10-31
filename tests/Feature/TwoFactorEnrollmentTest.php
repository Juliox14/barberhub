<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Support\TwoFactorAuthentication;
use App\Support\TwoFactorLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_two_factor_settings(): void
    {
        $this->get(route('two-factor.settings'))->assertRedirect(route('login'));
    }

    public function test_settings_page_requires_signed_in_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('two-factor.settings'))->assertOk();
    }

    public function test_enrollment_requires_current_password_and_does_not_flash_it(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from(route('two-factor.settings'))
            ->post(route('two-factor.begin'), ['current_password' => 'wrong-password']);

        $response->assertSessionHasErrors('current_password');
        $response->assertSessionMissing('_old_input.current_password');
        $this->assertNull($user->fresh()->two_factor_pending_secret);
    }

    public function test_enrollment_generates_a_locally_rendered_qr_after_password_confirmation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('two-factor.begin'), ['current_password' => 'password']);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $qrCode = $response->viewData('qrCode');
        $this->assertStringStartsWith('<?xml version="1.0"', $qrCode);
        $response->assertSee($qrCode, false);
        $response->assertDontSee('http://chart.googleapis.com');
        $pendingSecret = $user->fresh()->two_factor_pending_secret;
        $this->assertNotNull($pendingSecret);
        $this->assertNotSame($pendingSecret, DB::table('users')->where('id', $user->id)->value('two_factor_pending_secret'));
        $this->assertStringNotContainsString($pendingSecret, $user->fresh()->toJson());
    }

    public function test_repeating_enrollment_does_not_extend_the_fixed_grace_deadline(): void
    {
        $activationStartedAt = now()->startOfSecond();
        $user = User::factory()->create();
        $user->forceFill(['two_factor_enrollment_started_at' => $activationStartedAt])->save();

        $this->actingAs($user)->post(route('two-factor.begin'), ['current_password' => 'password'])->assertOk();
        $this->travelTo($activationStartedAt->copy()->addDays(10));
        $this->post(route('two-factor.begin'), ['current_password' => 'password'])->assertOk();

        $repeatedStart = $user->fresh()->two_factor_enrollment_started_at;
        $this->assertSame($activationStartedAt->toDateTimeString(), $repeatedStart->toDateTimeString());
        $this->assertSame(
            $activationStartedAt->copy()->addDays(14)->toDateTimeString(),
            $repeatedStart->copy()->addDays(14)->toDateTimeString(),
        );
    }

    public function test_enabled_factor_cannot_be_reset_by_starting_another_enrollment(): void
    {
        $user = $this->enabledUser();

        $this->actingAs($user)->post(route('two-factor.begin'), ['current_password' => 'password'])->assertConflict();
        $this->assertSame($user->two_factor_secret, $user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_pending_secret);
    }

    public function test_invalid_totp_does_not_confirm_pending_enrollment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('two-factor.begin'), ['current_password' => 'password']);

        $response = $this->post(route('two-factor.confirm'), ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertNull($user->fresh()->two_factor_recovery_codes);
        $this->assertSame(1, $user->fresh()->two_factor_version);
        $this->assertDatabaseMissing('security_audit_logs', ['event' => 'two_factor.enabled']);
    }

    public function test_valid_totp_confirms_enrollment_and_recovery_codes_are_shown_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('two-factor.begin'), ['current_password' => 'password']);
        $secret = $user->fresh()->two_factor_pending_secret;
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $response = $this->post(route('two-factor.confirm'), ['code' => $code]);

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private');
        $codes = $response->viewData('recoveryCodes');
        $this->assertCount(10, $codes);
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertNull($user->fresh()->two_factor_pending_secret);
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'two_factor.enabled']);
        $this->assertStringNotContainsString($secret, $user->fresh()->toJson());
        $this->get(route('two-factor.settings'))
            ->assertDontSee($codes[0])
            ->assertDontSee($secret);
        $auditMetadata = DB::table('security_audit_logs')->where('event', 'two_factor.enabled')->value('metadata');
        $this->assertSame([], json_decode($auditMetadata, true));
    }

    public function test_totp_replay_is_rejected_after_successful_verification(): void
    {
        $user = $this->enabledUser();
        $code = app(Google2FA::class)->getCurrentOtp($user->two_factor_secret);
        $service = app(TwoFactorAuthentication::class);

        $this->assertTrue($service->verify($user, $code, null));
        $this->assertFalse($service->verify($user->fresh(), $code, null));
    }

    public function test_expired_totp_is_rejected(): void
    {
        $user = $this->enabledUser();
        $twoFactor = app(Google2FA::class);
        $oldTimeStep = intdiv(now()->subMinutes(3)->timestamp, 30);
        $code = $twoFactor->oathTotp($user->two_factor_secret, $oldTimeStep);

        $this->assertFalse(app(TwoFactorAuthentication::class)->verify($user, $code, null));
    }

    public function test_recovery_code_is_consumed_once_and_audited_without_secret_metadata(): void
    {
        $user = $this->enabledUser();
        $codes = app(TwoFactorAuthentication::class)->regenerateRecoveryCodes($user);
        $user->refresh();
        $storedCodes = $user->two_factor_recovery_codes;
        $this->assertCount(10, $storedCodes);
        $this->assertNotSame($codes[0], $storedCodes[0]);
        $this->assertTrue(Hash::check($codes[0], $storedCodes[0]));

        $this->assertTrue(app(TwoFactorAuthentication::class)->verify($user, null, $codes[0]));
        $this->assertFalse(app(TwoFactorAuthentication::class)->verify($user->fresh(), null, $codes[0]));
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'two_factor.recovery_used']);
        $metadata = DB::table('security_audit_logs')->where('event', 'two_factor.recovery_used')->value('metadata');
        $this->assertSame([], json_decode($metadata, true));
        $this->assertCount(9, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_recovery_code_rotation_requires_the_current_password(): void
    {
        $user = $this->enabledUser();
        $codes = app(TwoFactorAuthentication::class)->regenerateRecoveryCodes($user);
        $this->assure($user->fresh());

        $this->actingAs($user)->post(route('two-factor.recovery'), [
            'current_password' => 'wrong-password',
            'code' => app(Google2FA::class)->getCurrentOtp($user->two_factor_secret),
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(app(TwoFactorAuthentication::class)->verify($user->fresh(), null, $codes[0]));
    }

    public function test_recovery_code_rotation_requires_a_valid_second_factor(): void
    {
        $user = $this->enabledUser();
        $service = app(TwoFactorAuthentication::class);
        $oldCode = $service->regenerateRecoveryCodes($user)[0];
        $this->assure($user->fresh());

        $this->actingAs($user)->post(route('two-factor.recovery'), [
            'current_password' => 'password',
            'recovery_code' => 'not-a-valid-recovery-code',
        ])->assertSessionHasErrors('code');

        $this->assertTrue($service->verify($user->fresh(), null, $oldCode));
    }

    public function test_rotating_recovery_codes_rejects_the_previous_code(): void
    {
        $user = $this->enabledUser();
        $service = app(TwoFactorAuthentication::class);
        $oldCode = $service->regenerateRecoveryCodes($user)[0];
        $this->assure($user->fresh());
        $this->actingAs($user)->post(route('two-factor.recovery'), [
            'current_password' => 'password',
            'code' => app(Google2FA::class)->getCurrentOtp($user->two_factor_secret),
        ])->assertOk();

        $this->assertFalse($service->verify($user->fresh(), null, $oldCode));
    }

    public function test_required_platform_user_cannot_disable_two_factor(): void
    {
        $user = $this->enabledUser(['is_platform_admin' => true]);

        $this->actingAs($user)->delete(route('two-factor.disable'), [
            'current_password' => 'password',
            'code' => app(Google2FA::class)->getCurrentOtp($user->two_factor_secret),
        ])->assertForbidden();
        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    public function test_disabling_two_factor_requires_the_current_password(): void
    {
        $user = $this->enabledUser();

        $this->actingAs($user)->delete(route('two-factor.disable'), [
            'current_password' => 'wrong-password',
            'code' => app(Google2FA::class)->getCurrentOtp($user->two_factor_secret),
        ])->assertSessionHasErrors('current_password');

        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    public function test_optional_user_can_disable_two_factor_after_both_confirmations(): void
    {
        $user = $this->enabledUser();

        $this->actingAs($user)->delete(route('two-factor.disable'), [
            'current_password' => 'password',
            'code' => app(Google2FA::class)->getCurrentOtp($user->two_factor_secret),
        ])->assertRedirect(route('two-factor.settings'));
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'two_factor.disabled']);
    }

    public function test_active_owner_or_admin_membership_requires_two_factor(): void
    {
        $user = User::factory()->create();
        $barbershop = Barbershop::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create([
            'role' => Membership::ROLE_ADMIN,
            'status' => Membership::STATUS_ACTIVE,
        ]);

        $this->assertTrue(app(TwoFactorAuthentication::class)->required($user));
    }

    public function test_inactive_admin_membership_does_not_require_two_factor(): void
    {
        $user = User::factory()->create();
        $barbershop = Barbershop::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create([
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_INACTIVE,
        ]);

        $this->assertFalse(app(TwoFactorAuthentication::class)->required($user));
    }

    public function test_recovery_attempt_limit_is_enforced_per_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('two-factor.begin'), ['current_password' => 'password'])->assertOk();

        for ($attempt = 0; $attempt < 7; $attempt++) {
            $response = $this->post(route('two-factor.confirm'), ['code' => '123456']);
        }

        $response->assertTooManyRequests();
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

    private function enabledUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill([
            'two_factor_secret' => app(Google2FA::class)->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
            'two_factor_enrollment_started_at' => now(),
        ])->save();

        $user = $user->fresh();
        $this->assure($user);

        return $user;
    }

    private function assure(User $user): void
    {
        $request = new Request;
        $request->setLaravelSession(app('session.store'));
        app(TwoFactorLogin::class)->markVerified($request, $user);
        $this->withSession($request->session()->all());
    }
}
