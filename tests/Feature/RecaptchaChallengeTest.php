<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RecaptchaChallengeTest extends TestCase
{
    use RefreshDatabase;

    private const SITE_KEY = 'test-site-key';

    private const SECRET_KEY = 'test-secret-key';

    private const TOKEN = 'test-recaptcha-token';

    public function test_ordinary_public_forms_keep_their_existing_validation_without_loading_google(): void
    {
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();

        foreach (['/login', '/register', '/forgot-password'] as $url) {
            $this->get($url)->assertDontSee('google.com/recaptcha');
        }

        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
        $this->post('/register', [])->assertSessionHasErrors(['name', 'email', 'password']);
        $this->post('/forgot-password', [])->assertSessionHasErrors('email');
        Http::assertNothingSent();
        $this->assertDatabaseCount('users', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_fourth_login_post_requires_a_challenge_and_rejected_token_is_not_admitted(): void
    {
        $this->enableRecaptcha();
        Http::preventStrayRequests();

        foreach (range(1, 3) as $attempt) {
            $this->post('/login', ['email' => "person{$attempt}@example.com"])
                ->assertSessionHasErrors('password');
        }

        $this->from('/login')->post('/login', [])->assertSessionHasErrors('g-recaptcha-response');
        Http::assertNothingSent();
        $this->from('/login')->post('/login', [])->assertSessionHasErrors('g-recaptcha-response');
    }

    public function test_each_public_flow_has_its_own_ip_counter_and_role_or_email_does_not_change_it(): void
    {
        $this->enableRecaptcha();
        Http::preventStrayRequests();

        $flows = [
            '/login' => ['password'],
            '/register' => ['name', 'email', 'password'],
            '/forgot-password' => ['email'],
        ];

        foreach ($flows as $url => $expectedErrors) {
            foreach (range(1, 3) as $attempt) {
                $payload = $url === '/login' ? ['email' => "identity{$attempt}@example.com"] : [];
                $this->post($url, $payload)->assertSessionHasErrors($expectedErrors);
            }

            $this->from($url)->post($url, [])->assertSessionHasErrors('g-recaptcha-response');
        }

        Http::assertNothingSent();
    }

    public function test_counter_is_independent_of_client_ip_and_browser_cookies(): void
    {
        $this->enableRecaptcha();

        foreach (range(1, 3) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.80'])
                ->post('/login', ['email' => "same-ip{$attempt}@example.com"])
                ->assertSessionHasErrors('password');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.81'])
            ->post('/login', ['email' => 'other-ip@example.com'])
            ->assertSessionHasErrors('password');

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.80'])
            ->withCookie('browser-id', 'different-browser')
            ->from('/login')->post('/login', [])
            ->assertSessionHasErrors('g-recaptcha-response');
    }

    public function test_login_challenge_does_not_exempt_an_account_based_on_its_role(): void
    {
        $this->enableRecaptcha();
        $roles = ['barber', 'client', 'owner'];

        foreach ($roles as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->post('/login', ['email' => $user->email])
                ->assertSessionHasErrors('password');
        }

        $this->from('/login')->post('/login', [])->assertSessionHasErrors('g-recaptcha-response');
    }

    public function test_counter_expires_after_fifteen_minutes_and_challenges_on_the_exact_fourth_post(): void
    {
        $this->freezeTime();
        $this->enableRecaptcha();

        foreach (range(1, 3) as $attempt) {
            $this->post('/login', ['email' => "expiry{$attempt}@example.com"])
                ->assertSessionHasErrors('password');
        }

        $this->from('/login')->post('/login', [])->assertSessionHasErrors('g-recaptcha-response');
        $this->travel(15)->minutes();
        $this->post('/login', ['email' => 'after-expiry@example.com'])
            ->assertSessionHasErrors('password');
        $this->get('/login')->assertDontSee('g-recaptcha');
        $this->travelBack();
    }

    public function test_challenge_is_rendered_only_after_threshold_and_missing_configuration_fails_closed(): void
    {
        $this->enableRecaptcha();

        foreach (range(1, 3) as $attempt) {
            $this->post('/register', [])->assertSessionHasErrors(['name', 'email', 'password']);
        }

        $this->get('/register')->assertSee('g-recaptcha')->assertSee(self::SITE_KEY)
            ->assertSee('data-theme="dark"', false)
            ->assertSee('data-size="compact"', false);

        config([
            'recaptcha.site_key' => null,
            'recaptcha.secret_key' => null,
            'recaptcha.trusted_hostnames' => [],
        ]);

        $this->get('/register')->assertSee('no está disponible')->assertDontSee(self::SECRET_KEY);
        $this->from('/register')->post('/register', [])->assertSessionHasErrors('g-recaptcha-response');
    }

    public function test_valid_challenge_reaches_registration_and_sends_one_provider_verification(): void
    {
        $this->enableRecaptcha();
        $this->admitThreeRegistrationPosts();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
            ]),
        ]);

        $response = $this->post('/register', [
            'name' => 'Nuevo Cliente',
            'email' => 'new-client@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'g-recaptcha-response' => self::TOKEN,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['email' => 'new-client@example.com']);
        $this->assertAuthenticated();
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
            && $request->method() === 'POST'
            && $request['secret'] === self::SECRET_KEY
            && $request['response'] === self::TOKEN
            && $request['remoteip'] === '127.0.0.1'
            && str_starts_with($request->header('Content-Type')[0] ?? '', 'application/x-www-form-urlencoded'));
        Http::assertSentCount(1);
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
        $this->assertStringNotContainsString(self::SECRET_KEY, $response->getContent());
    }

    public function test_valid_login_challenge_reaches_existing_authentication_flow(): void
    {
        $this->enableRecaptcha();
        $user = User::factory()->create([
            'password' => bcrypt('StrongPassword123!'),
        ]);
        $this->admitThreeLoginPosts();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
            ]),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
            'g-recaptcha-response' => self::TOKEN,
        ])->assertForbidden();

        $this->assertAuthenticatedAs($user);
        Http::assertSentCount(1);
    }

    public function test_valid_password_recovery_challenge_reaches_existing_notification_flow(): void
    {
        $this->enableRecaptcha();
        $user = User::factory()->create();
        $this->admitThreeForgotPasswordPosts();
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
            ]),
        ]);

        $this->post('/forgot-password', [
            'email' => $user->email,
            'g-recaptcha-response' => self::TOKEN,
        ])->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class);
        Http::assertSentCount(1);
    }

    public function test_rejected_registration_challenge_prevents_user_and_notification_side_effects(): void
    {
        $this->enableRecaptcha();
        Mail::fake();
        Notification::fake();
        $this->admitThreeRegistrationPosts();
        $userCount = User::query()->count();
        $auditCount = DB::table('security_audit_logs')->count();
        $resetTokens = DB::table('password_reset_tokens')->count();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $this->from('/register')->post('/register', [
            'name' => 'Rejected User',
            'email' => 'rejected-registration@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'g-recaptcha-response' => self::TOKEN,
        ])->assertRedirect('/register')->assertSessionHasErrors('g-recaptcha-response')
            ->assertSessionHas('_old_input.email', 'rejected-registration@example.com')
            ->assertSessionMissing('_old_input.g-recaptcha-response');

        $this->assertGuest();
        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($auditCount, DB::table('security_audit_logs')->count());
        $this->assertSame($resetTokens, DB::table('password_reset_tokens')->count());
        Notification::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_rejected_recovery_challenge_preserves_user_reset_token_and_sends_nothing(): void
    {
        $this->enableRecaptcha();
        $user = User::factory()->create();
        $this->admitThreeForgotPasswordPosts();
        Password::broker()->createToken($user);
        $userBefore = $user->fresh()->getRawOriginal();
        $resetTokensBefore = DB::table('password_reset_tokens')->orderBy('email')->get()
            ->map(static fn (object $row): array => (array) $row)->all();
        $auditCount = DB::table('security_audit_logs')->count();
        Mail::fake();
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $this->from('/forgot-password')->post('/forgot-password', [
            'email' => $user->email,
            'g-recaptcha-response' => self::TOKEN,
        ])->assertRedirect('/forgot-password')->assertSessionHasErrors('g-recaptcha-response')
            ->assertSessionHas('_old_input.email', $user->email)
            ->assertSessionMissing('_old_input.g-recaptcha-response');

        $this->assertGuest();
        $this->assertSame($userBefore, $user->fresh()->getRawOriginal());
        $this->assertSame(
            $resetTokensBefore,
            DB::table('password_reset_tokens')->orderBy('email')->get()
                ->map(static fn (object $row): array => (array) $row)->all(),
        );
        $this->assertSame($auditCount, DB::table('security_audit_logs')->count());
        Notification::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_invalid_duplicate_and_malformed_provider_responses_fail_closed_without_flashing_token(): void
    {
        $this->enableRecaptcha();
        $this->admitThreeRegistrationPosts();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['timeout-or-duplicate'],
            ]),
        ]);

        $this->from('/register')->post('/register', ['g-recaptcha-response' => self::TOKEN])
            ->assertSessionHasErrors('g-recaptcha-response')
            ->assertSessionMissing('_old_input.g-recaptcha-response');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response('not-json'),
        ]);
        $this->from('/register')->post('/register', ['g-recaptcha-response' => self::TOKEN])
            ->assertSessionHasErrors('g-recaptcha-response')
            ->assertSessionMissing('_old_input.g-recaptcha-response');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_hostname_mismatch_timeout_and_http_errors_reject_without_business_side_effects(): void
    {
        $this->enableRecaptcha();
        $this->admitThreeRegistrationPosts();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'attacker.example',
            ]),
        ]);
        $this->from('/register')->post('/register', ['g-recaptcha-response' => self::TOKEN])
            ->assertSessionHasErrors('g-recaptcha-response');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::failedConnection(),
        ]);
        $this->from('/register')->post('/register', ['g-recaptcha-response' => self::TOKEN])
            ->assertSessionHasErrors('g-recaptcha-response');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([], 503),
        ]);
        $this->from('/register')->post('/register', ['g-recaptcha-response' => self::TOKEN])
            ->assertSessionHasErrors('g-recaptcha-response');

        $this->assertDatabaseCount('users', 0);
        Http::assertSentCount(1);
    }

    public function test_rejected_login_challenge_does_not_authenticate_or_mutate_account_session_or_failure_state(): void
    {
        $this->enableRecaptcha();
        $user = User::factory()->create();
        $user->forceFill(['active_session_id' => 'existing-owner', 'active_session_at' => now()])->save();
        $before = $user->fresh()->getRawOriginal();
        $userCount = User::query()->count();
        $auditCount = DB::table('security_audit_logs')->count();
        $resetTokensBefore = DB::table('password_reset_tokens')->count();
        Mail::fake();
        Notification::fake();
        $this->admitThreeLoginPosts();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'g-recaptcha-response' => self::TOKEN,
        ])->assertSessionHasErrors('g-recaptcha-response')
            ->assertSessionMissing('_old_input.g-recaptcha-response');

        $this->assertGuest();
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($auditCount, DB::table('security_audit_logs')->count());
        $this->assertSame($resetTokensBefore, DB::table('password_reset_tokens')->count());
        Notification::assertNothingSent();
        Mail::assertNothingOutgoing();
    }

    public function test_invalid_json_challenge_token_is_not_flashed_after_an_html_redirect(): void
    {
        $this->enableRecaptcha();
        $this->admitThreeRegistrationPosts();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $response = $this->from('/register')->postJsonAsHtml('/register', [
            'name' => 'JSON User',
            'email' => 'json-user@example.com',
            'password' => 'WeakPassword',
            'password_confirmation' => 'WeakPassword',
            'g-recaptcha-response' => self::TOKEN,
        ]);

        $response->assertRedirect('/register')->assertSessionHasErrors('g-recaptcha-response')
            ->assertSessionHas('_old_input.email', 'json-user@example.com')
            ->assertSessionMissing('_old_input.g-recaptcha-response');
    }

    public function test_json_token_is_not_flashed_on_downstream_validation_failure_below_threshold(): void
    {
        $this->enableRecaptcha();

        $this->from('/register')->postJsonAsHtml('/register', [
            'email' => 'below-threshold@example.com',
            'g-recaptcha-response' => self::TOKEN,
        ])->assertRedirect('/register')
            ->assertSessionHasErrors(['name', 'password'])
            ->assertSessionHas('_old_input.email', 'below-threshold@example.com')
            ->assertSessionMissing('_old_input.g-recaptcha-response');
    }

    public function test_disabled_json_request_sanitizes_token_without_changing_validation(): void
    {
        $this->from('/register')->postJsonAsHtml('/register', [
            'email' => 'disabled@example.com',
            'g-recaptcha-response' => self::TOKEN,
        ])->assertRedirect('/register')
            ->assertSessionHasErrors(['name', 'password'])
            ->assertSessionHas('_old_input.email', 'disabled@example.com')
            ->assertSessionMissing('_old_input.g-recaptcha-response');
    }

    public function test_json_validation_response_does_not_include_the_challenge_token_or_secret(): void
    {
        $this->enableRecaptcha();
        $this->admitThreeRegistrationPosts();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $response = $this->postJson('/register', ['g-recaptcha-response' => self::TOKEN]);

        $response->assertUnprocessable()->assertJsonValidationErrors('g-recaptcha-response');
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
        $this->assertStringNotContainsString(self::SECRET_KEY, $response->getContent());
    }

    public function test_missing_or_oversized_tokens_fail_before_provider_requests(): void
    {
        $this->enableRecaptcha();
        $this->admitThreeLoginPosts();
        Http::preventStrayRequests();

        $this->from('/login')->post('/login', [])->assertSessionHasErrors('g-recaptcha-response');
        $this->from('/login')->post('/login', ['g-recaptcha-response' => str_repeat('x', 4097)])
            ->assertSessionHasErrors('g-recaptcha-response');
        Http::assertNothingSent();
    }

    private function postJsonAsHtml(string $uri, array $payload): TestResponse
    {
        return $this->json('POST', $uri, $payload, ['Accept' => 'text/html']);
    }

    private function enableRecaptcha(): void
    {
        config([
            'cache.default' => 'array',
            'recaptcha.enabled' => true,
            'recaptcha.site_key' => self::SITE_KEY,
            'recaptcha.secret_key' => self::SECRET_KEY,
            'recaptcha.trusted_hostnames' => ['localhost'],
        ]);
    }

    private function admitThreeLoginPosts(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->post('/login', ['email' => "login{$attempt}@example.com"])
                ->assertSessionHasErrors('password');
        }
    }

    private function admitThreeRegistrationPosts(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->post('/register', [])->assertSessionHasErrors(['name', 'email', 'password']);
        }
    }

    private function admitThreeForgotPasswordPosts(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->post('/forgot-password', [])->assertSessionHasErrors('email');
        }
    }
}
