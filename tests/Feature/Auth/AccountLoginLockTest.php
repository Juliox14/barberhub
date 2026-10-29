<?php

namespace Tests\Feature\Auth;

use App\Events\AccountLoginLocked;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Support\SingleDeviceSession;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AccountLoginLockTest extends TestCase
{
    use RefreshDatabase;

    private const LOCK_NOTICE = 'El acceso está bloqueado temporalmente. Intentá más tarde o usá «¿Olvidaste tu contraseña?» para recuperar el acceso.';

    public function test_threshold_and_subsequent_attempts_explain_temporary_block_without_extending_or_revealing_credentials(): void
    {
        $this->freezeTime();
        Notification::fake();
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])->from('/login')
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
            $response->assertRedirect('/login')->assertSessionHasErrors('email');
            $this->assertSame($attempt === 10 ? self::LOCK_NOTICE : trans('auth.failed'), $this->firstEmailError($response));
        }

        $lockedState = $user->fresh()->getAttributes();
        for ($attempt = 0; $attempt < 7; $attempt++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])->from('/login')
                ->post('/login', ['email' => $user->email, 'password' => 'password']);
            $response->assertRedirect('/login')->assertSessionHasErrors('email');
            $this->assertSame(self::LOCK_NOTICE, $this->firstEmailError($response));
            $this->assertSame($lockedState, $user->fresh()->getAttributes());
            $this->assertGuest();
        }

        $page = $this->withCookie(config('session.cookie'), $response->getSession()->getId())
            ->get('/login')->assertOk()->assertSee(self::LOCK_NOTICE)
            ->assertSee(route('password.request'), false)->assertDontSee('wrong-password');
        $this->assertSame(1, substr_count($page->getContent(), self::LOCK_NOTICE));
        Notification::assertNothingSent();
    }

    public function test_tenth_failed_attempt_locks_account_across_ips_without_extending_lock(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create([
            'active_session_id' => str_repeat('a', 64),
            'active_session_at' => now(),
            'remember_token' => str_repeat('b', 60),
        ]);
        Event::fake([AccountLoginLocked::class]);

        for ($attempt = 0; $attempt < 9; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->from('/login')
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertRedirect('/login');
        }

        $this->assertSame(9, (int) $user->fresh()->login_failed_attempts);
        $this->assertNull($user->fresh()->login_locked_until);
        Event::assertNotDispatched(AccountLoginLocked::class);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.9'])
            ->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/login');

        $lockedState = $this->securityState($user);
        $this->assertSame(10, (int) $lockedState['login_failed_attempts']);
        $this->assertSame(str_repeat('a', 64), $lockedState['active_session_id']);
        $this->assertSame(str_repeat('b', 60), $lockedState['remember_token']);
        $this->assertSame(now()->startOfSecond()->addMinutes(15)->toISOString(), Carbon::parse($lockedState['login_locked_until'])->toISOString());
        Event::assertDispatchedTimes(AccountLoginLocked::class, 1);
        Event::assertDispatched(AccountLoginLocked::class, fn (AccountLoginLocked $event): bool => $event->userId === $user->id && $event->lockedUntil === now()->startOfSecond()->addMinutes(15)->toISOString()
        );

        $this->travel(5)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
            ->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/login');

        $this->assertSame($lockedState, $this->securityState($user));
        Event::assertDispatchedTimes(AccountLoginLocked::class, 1);
        $this->assertGuest();
    }

    public function test_failures_outside_the_fifteen_minute_window_do_not_accumulate(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->from('/login')
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->assertSame(5, (int) $user->fresh()->login_failed_attempts);
        $this->travel(15)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
            ->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $this->assertSame(1, (int) $user->fresh()->login_failed_attempts);
        $this->assertSame(now()->toDateTimeString(), Carbon::parse($user->fresh()->getAttribute('login_failed_window_started_at'))->toDateTimeString());
        $this->assertNull($user->fresh()->login_locked_until);
    }

    public function test_unknown_email_and_wrong_password_have_same_public_failure_without_unknown_state(): void
    {
        $user = User::factory()->create();
        $unknownEmail = 'unknown-lock-test@example.test';

        $knownResponse = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
        $knownResponse->assertRedirect('/login');
        $knownMessage = $this->firstEmailError($knownResponse);

        $unknownResponse = $this->from('/login')->post('/login', [
            'email' => $unknownEmail,
            'password' => 'wrong-password',
        ]);
        $unknownResponse->assertRedirect('/login');
        $unknownMessage = $this->firstEmailError($unknownResponse);

        $this->assertSame($knownMessage, $unknownMessage);
        $this->assertSame(trans('auth.failed'), $unknownMessage);
        $this->assertDatabaseMissing('users', ['email' => $unknownEmail]);
    }

    public function test_existing_email_ip_limiter_still_throttles_after_five_failures(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.80'])
                ->from('/login')
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $throttledResponse = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.80'])
            ->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $throttledResponse->assertRedirect('/login');
        $this->assertNotSame(trans('auth.failed'), $this->firstEmailError($throttledResponse));
        $this->assertSame(5, (int) $user->fresh()->login_failed_attempts);
    }

    public function test_external_failures_preserve_active_session_and_password_recovery_access(): void
    {
        $user = User::factory()->create([
            'active_session_id' => str_repeat('a', 64),
            'active_session_at' => now(),
            'remember_token' => str_repeat('b', 60),
        ]);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->from('/login')
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        Notification::fake();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'active_session_id' => str_repeat('a', 64),
            'remember_token' => str_repeat('b', 60),
        ]);
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_authenticated_browser_remains_usable_after_external_failures(): void
    {
        config(['session.driver' => 'database']);
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        $user = User::factory()->create();
        $barbershop = Barbershop::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create();

        $login = $this->freshBrowser()->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $login->assertRedirect();
        $sessionId = $login->getCookie(config('session.cookie'))->getValue();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->freshBrowser()
                ->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->from('/login')
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertRedirect('/login');
        }

        $this->browser($sessionId)->get('/profile')->assertOk();
    }

    public function test_lock_expires_after_fifteen_minutes_and_allows_a_fresh_login(): void
    {
        $user = User::factory()->create();
        $barbershop = Barbershop::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->from('/login')
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->travel(15)->minutes();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, $user->fresh()->login_failed_attempts);
        $this->assertNull($user->fresh()->login_locked_until);
    }

    public function test_pending_transfer_cannot_grant_after_session_revocation_changes_version(): void
    {
        $user = User::factory()->create();
        $sessionId = $this->get('/login')->getCookie(config('session.cookie'))->getValue();

        $this->withCookie(config('session.cookie'), $sessionId)->withSession([
            SingleDeviceSession::PENDING_KEY => [
                'user_id' => $user->id,
                'remember' => false,
                'session_id' => $sessionId,
                'password_fingerprint' => $user->password,
                'security_session_version' => 0,
                'created_at' => now()->timestamp,
            ],
        ]);
        app(SingleDeviceSession::class)->revoke($user);

        $this->post('/login/session-conflict/confirm')->assertStatus(419);
        $this->assertGuest();
        $this->assertSame(1, (int) $user->fresh()->security_session_version);
    }

    public function test_lock_blocks_a_pending_session_transfer_grant(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->from('/login')
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $loginPage = $this->get('/login');
        $sessionId = $loginPage->getCookie(config('session.cookie'))->getValue();
        $this->withCookie(config('session.cookie'), $sessionId)
            ->withSession([
                SingleDeviceSession::PENDING_KEY => [
                    'user_id' => $user->id,
                    'remember' => false,
                    'session_id' => $sessionId,
                    'password_fingerprint' => $user->password,
                    'security_session_version' => 0,
                    'created_at' => now()->timestamp,
                ],
            ])
            ->post('/login/session-conflict/confirm')
            ->assertStatus(419);

        $this->assertGuest();
    }

    private function browser(string $sessionId): self
    {
        $this->resetBrowserSessionBindings();

        return $this->withCookie(config('session.cookie'), $sessionId);
    }

    private function freshBrowser(): self
    {
        return $this->browser(Str::random(40));
    }

    private function resetBrowserSessionBindings(): void
    {
        Auth::forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
    }

    private function firstEmailError(TestResponse $response): ?string
    {
        $errors = $response->getSession()->get('errors');

        return is_array($errors)
            ? ($errors['default']['messages']['email'][0] ?? null)
            : $errors->first('email');
    }

    /** @return array<string, int|string|null> */
    private function securityState(User $user): array
    {
        $freshUser = $user->fresh();

        return [
            'login_failed_attempts' => (int) $freshUser->getAttribute('login_failed_attempts'),
            'login_failed_window_started_at' => $freshUser->getAttribute('login_failed_window_started_at'),
            'login_locked_until' => $freshUser->getAttribute('login_locked_until'),
            'active_session_id' => $freshUser->getAttribute('active_session_id'),
            'active_session_at' => $freshUser->getRawOriginal('active_session_at'),
            'remember_token' => $freshUser->getAttribute('remember_token'),
        ];
    }

    public function test_lock_expires_and_successful_login_clears_failed_window(): void
    {
        $user = User::factory()->create();
        $barbershop = Barbershop::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create();

        for ($attempt = 0; $attempt < 9; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
                ->from('/login')
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])
            ->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect();

        $this->assertSame(0, $user->fresh()->login_failed_attempts);
    }
}
