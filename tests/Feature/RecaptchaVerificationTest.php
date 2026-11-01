<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdaptiveChallengeTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RecaptchaVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const SITE_KEY = 'test-site-key';

    private const SECRET_KEY = 'test-secret-key';

    private const CAPTCHA_TOKEN = 'one-time-google-token';

    private const TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    public function test_successful_challenge_survives_canceling_two_factor_login_for_five_minutes(): void
    {
        $this->freezeTime();
        $this->enableRecaptcha();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
            ]),
        ]);

        $user = User::factory()->create([
            'is_platform_admin' => true,
            'two_factor_secret' => self::TWO_FACTOR_SECRET,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => null,
            'active_session_id' => 'legitimate-owner',
            'active_session_at' => now(),
        ]);

        foreach (range(1, 3) as $attempt) {
            $this->post('/login', ['email' => "unknown{$attempt}@example.com"])
                ->assertSessionHasErrors('password');
        }

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'g-recaptcha-response' => self::CAPTCHA_TOKEN,
        ])->assertRedirect('/two-factor/challenge');

        $this->assertGuest();
        $this->assertStringNotContainsString(self::CAPTCHA_TOKEN, serialize(session()->all()));
        $this->assertStringNotContainsString(self::SECRET_KEY, serialize(session()->all()));
        $this->assertSame('legitimate-owner', $user->fresh()->active_session_id);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
            && $request['response'] === self::CAPTCHA_TOKEN
            && $request['secret'] === self::SECRET_KEY);

        $this->post('/two-factor/cancel')->assertRedirect('/login');
        $loginPage = $this->get('/login');
        $retryResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $retryResponse->assertRedirect('/two-factor/challenge');
        $loginPage->assertDontSee('data-recaptcha-modal', false)
            ->assertDontSee('google.com/recaptcha/api.js');

        $this->assertGuest();
        $this->assertSame('legitimate-owner', $user->fresh()->active_session_id);
        Http::assertSentCount(1);
        $this->assertSame(5, app(AdaptiveChallengeTracker::class)->count('login', '127.0.0.1'));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
        Http::assertSentCount(1);

        $this->travel(5)->minutes();
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('g-recaptcha-response');
        Http::assertSentCount(1);
        $this->travelBack();
    }

    public function test_successful_proofs_are_independent_for_registration_and_password_recovery(): void
    {
        $this->enableRecaptcha();
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
            ]),
        ]);

        foreach (range(1, 3) as $attempt) {
            $this->post('/register', [])->assertSessionHasErrors(['name', 'email', 'password']);
        }
        $this->post('/register', ['g-recaptcha-response' => self::CAPTCHA_TOKEN])
            ->assertSessionHasErrors(['name', 'email', 'password']);
        $this->post('/register', [])->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertDatabaseCount('users', 0);

        foreach (range(1, 3) as $attempt) {
            $this->post('/forgot-password', [])->assertSessionHasErrors('email');
        }
        $this->get('/forgot-password')->assertSee('data-recaptcha-modal', false)
            ->assertSee('google.com/recaptcha/api.js');
        $this->post('/forgot-password', ['g-recaptcha-response' => self::CAPTCHA_TOKEN])
            ->assertSessionHasErrors('email');
        $this->post('/forgot-password', [])->assertSessionHasErrors('email');

        Http::assertSentCount(2);
        Notification::assertNothingSent();
        $this->assertStringNotContainsString(self::CAPTCHA_TOKEN, serialize(session()->all()));
        $this->assertStringNotContainsString(self::SECRET_KEY, serialize(session()->all()));
    }

    public function test_proof_is_rejected_after_the_client_ip_changes(): void
    {
        $this->enableRecaptcha();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
            ]),
        ]);
        $this->issueLoginProof();

        foreach (range(1, 3) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.84'])
                ->post('/login', ['email' => "other-ip{$attempt}@example.com"])
                ->assertSessionHasErrors('password');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.84'])
            ->get('/login')->assertSee('data-recaptcha-modal', false)
            ->assertSee('google.com/recaptcha/api.js');
        Http::assertSentCount(1);
    }

    public function test_proof_is_rejected_in_a_new_browser_session(): void
    {
        $this->enableRecaptcha();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
            ]),
        ]);
        $this->issueLoginProof();

        $this->withCookie(config('session.cookie'), 'different-browser-session')
            ->get('/login')->assertSee('data-recaptcha-modal', false)
            ->assertSee('google.com/recaptcha/api.js');
        Http::assertSentCount(1);
    }

    public function test_configuration_change_invalidates_an_existing_proof(): void
    {
        $this->enableRecaptcha();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
            ]),
        ]);
        $this->issueLoginProof();
        config(['recaptcha.site_key' => 'changed-site-key']);

        $this->get('/login')->assertSee('data-recaptcha-modal', false)
            ->assertSee('changed-site-key');
        Http::assertSentCount(1);
    }

    public function test_a_request_while_disabled_discards_proof_before_reactivation(): void
    {
        $this->enableRecaptcha();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
            ]),
        ]);
        $this->issueLoginProof();
        $this->post('/two-factor/cancel')->assertRedirect('/login');

        config(['recaptcha.enabled' => false]);
        $this->get('/login')->assertOk()->assertDontSee('data-recaptcha-modal', false);

        config(['recaptcha.enabled' => true]);
        $this->get('/login')->assertOk()->assertSee('data-recaptcha-modal', false);
        $this->from('/login')->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])->assertRedirect('/login')->assertSessionHasErrors('g-recaptcha-response');

        $this->assertGuest();
        Http::assertSentCount(1);
        $this->assertSame(4, app(AdaptiveChallengeTracker::class)->count('login', '127.0.0.1'));
    }

    public function test_failed_or_disabled_verification_never_mints_a_proof(): void
    {
        $this->enableRecaptcha();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        foreach (range(1, 3) as $attempt) {
            $this->post('/login', ['email' => "unverified{$attempt}@example.com"])
                ->assertSessionHasErrors('password');
        }
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'password', 'g-recaptcha-response' => self::CAPTCHA_TOKEN])
            ->assertSessionHasErrors('g-recaptcha-response')
            ->assertSessionMissing('adaptive_recaptcha_verifications.login');

        config(['recaptcha.trusted_hostnames' => []]);
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'password', 'g-recaptcha-response' => self::CAPTCHA_TOKEN])
            ->assertSessionHasErrors('g-recaptcha-response')
            ->assertSessionMissing('adaptive_recaptcha_verifications.login');

        config(['recaptcha.enabled' => false]);
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'password', 'g-recaptcha-response' => self::CAPTCHA_TOKEN])
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('adaptive_recaptcha_verifications.login');
        Http::assertSentCount(1);
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

    private function issueLoginProof(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => true,
            'two_factor_secret' => self::TWO_FACTOR_SECRET,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => null,
        ]);

        foreach (range(1, 3) as $attempt) {
            $this->post('/login', ['email' => "proof{$attempt}@example.com"])
                ->assertSessionHasErrors('password');
        }

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'g-recaptcha-response' => self::CAPTCHA_TOKEN,
        ])->assertRedirect('/two-factor/challenge');
        $this->assertGuest();
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
}
