<?php

namespace Tests\Feature\Auth;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\User;
use App\Support\CustomerInvitationService;
use App\Support\SingleDeviceSession;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SingleDeviceSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
    }

    public function test_transfer_requires_confirmation_then_rejects_old_browser_on_protected_request(): void
    {
        $user = $this->tenantUser();
        $oldSessionId = $this->loginAndGetSessionId($user);

        $conflict = $this->freshBrowser()->post('/login', $this->credentials($user));
        $conflict->assertOk();
        $conflict->assertSee('Ya hay una sesión abierta');
        $conflict->assertSee('Salir');
        $conflict->assertSee('role="dialog"', false);
        $conflict->assertSee('aria-modal="true"', false);
        $newSessionId = $this->sessionIdFrom($conflict);
        Auth::forgetGuards();
        $this->assertGuest();

        $transfer = $this->browser($newSessionId)->post('/login/session-conflict/confirm');
        $transfer->assertRedirect();
        $newSessionId = $this->sessionIdFrom($transfer);

        Auth::forgetGuards();
        $this->browser($oldSessionId)->get('/profile')->assertRedirect('/login');
        Auth::forgetGuards();
        $this->browser($newSessionId)->get('/profile')->assertOk();
    }

    public function test_cancelling_conflict_keeps_old_browser_usable_and_new_browser_guest(): void
    {
        $user = $this->tenantUser();
        $oldSessionId = $this->loginAndGetSessionId($user);
        $conflict = $this->freshBrowser()->post('/login', $this->credentials($user));
        $newSessionId = $this->sessionIdFrom($conflict);

        $this->browser($newSessionId)->post('/login/session-conflict/cancel')->assertRedirect('/login');
        Auth::forgetGuards();
        $this->browser($newSessionId)->get('/profile')->assertRedirect('/login');
        Auth::forgetGuards();
        $this->browser($oldSessionId)->get('/profile')->assertOk();
    }

    public function test_invalid_password_does_not_disclose_conflict_and_clears_pending_confirmation(): void
    {
        $user = $this->tenantUser();
        $this->loginAndGetSessionId($user);
        $conflict = $this->freshBrowser()->post('/login', $this->credentials($user));
        $guestSessionId = $this->sessionIdFrom($conflict);

        $response = $this->browser($guestSessionId)->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'incorrect',
        ]);
        $response->assertRedirect('/login');
        $this->assertSame(
            'El correo electrónico o la contraseña son incorrectos.',
            $response->getSession()->get('errors')->first('email'),
        );
        $this->browser($guestSessionId)->post('/login/session-conflict/confirm')->assertStatus(419);
    }

    public function test_malformed_login_attempt_clears_pending_confirmation(): void
    {
        $user = $this->tenantUser();
        $this->loginAndGetSessionId($user);
        $conflict = $this->freshBrowser()->post('/login', $this->credentials($user));
        $guestSessionId = $this->sessionIdFrom($conflict);

        $this->browser($guestSessionId)->post('/login', ['email' => $user->email])->assertRedirect();
        $this->browser($guestSessionId)->post('/login/session-conflict/confirm')->assertStatus(419);
    }

    public function test_confirmation_rejects_forged_identity_without_disturbing_the_active_owner(): void
    {
        $user = $this->tenantUser();
        $oldSessionId = $this->loginAndGetSessionId($user);
        $attacker = User::factory()->create();

        $this->freshBrowser()->post('/login/session-conflict/confirm', ['user_id' => $attacker->id])->assertStatus(419);
        $this->browser($oldSessionId)->get('/profile')->assertOk();
    }

    public function test_expired_confirmation_is_rejected_without_changing_the_existing_session(): void
    {
        $user = $this->tenantUser();
        $oldSessionId = $this->loginAndGetSessionId($user);
        $conflict = $this->freshBrowser()->post('/login', $this->credentials($user));
        $guestSessionId = $this->sessionIdFrom($conflict);

        $this->travel(11)->minutes();
        $this->browser($guestSessionId)->post('/login/session-conflict/confirm')->assertStatus(419);
        Auth::forgetGuards();
        $this->browser($oldSessionId)->get('/profile')->assertOk();
    }

    public function test_replayed_confirmation_is_rejected(): void
    {
        $user = $this->tenantUser();
        $this->loginAndGetSessionId($user);
        $conflict = $this->freshBrowser()->post('/login', $this->credentials($user));
        $guestSessionId = $this->sessionIdFrom($conflict);

        $confirmed = $this->browser($guestSessionId)->post('/login/session-conflict/confirm');
        $confirmed->assertRedirect();
        Auth::forgetGuards();
        $this->browser($guestSessionId)->post('/login/session-conflict/confirm')->assertStatus(419);
    }

    public function test_password_change_after_conflict_rejects_pending_confirmation_without_transfer(): void
    {
        $user = $this->tenantUser();
        $oldSessionId = $this->loginAndGetSessionId($user);
        $conflict = $this->freshBrowser()->post('/login', $this->credentials($user));
        $guestSessionId = $this->sessionIdFrom($conflict);
        $user->forceFill(['password' => Hash::make('changed-password')])->save();

        $this->browser($guestSessionId)->post('/login/session-conflict/confirm')->assertStatus(419);
        Auth::forgetGuards();
        $this->browser($oldSessionId)->get('/profile')->assertOk();
    }

    public function test_idle_session_does_not_block_valid_login_and_old_session_cannot_reclaim(): void
    {
        $user = $this->tenantUser();
        $oldSessionId = $this->loginAndGetSessionId($user);
        $this->travel(121)->minutes();

        $newLogin = $this->freshBrowser()->post('/login', $this->credentials($user));
        $newLogin->assertRedirect();
        $newSessionId = $this->sessionIdFrom($newLogin);
        Auth::forgetGuards();
        $this->browser($oldSessionId)->get('/profile')->assertRedirect('/login');
        Auth::forgetGuards();
        $this->browser($newSessionId)->get('/profile')->assertOk();
    }

    public function test_old_session_stays_rejected_after_replacement_logs_out(): void
    {
        $user = $this->tenantUser();
        $oldSessionId = $this->loginAndGetSessionId($user);
        $conflict = $this->freshBrowser()->post('/login', $this->credentials($user));
        $newSessionId = $this->sessionIdFrom($conflict);
        $confirmed = $this->browser($newSessionId)->post('/login/session-conflict/confirm');
        $newSessionId = $this->sessionIdFrom($confirmed);

        $this->browser($newSessionId)->post('/logout');
        $this->travel(121)->minutes();
        Auth::forgetGuards();
        $this->browser($oldSessionId)->get('/profile')->assertRedirect('/login');
    }

    public function test_registration_does_not_authenticate_when_a_competing_claim_wins(): void
    {
        $competingSessionId = null;
        $competingOwnerToken = null;
        $this->listenForCompetingSessionClaim($competingSessionId, $competingOwnerToken);

        $response = $this->freshBrowser()->post('/register', [
            'name' => 'Nueva Clienta',
            'email' => 'racing-client@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('errors');
        Auth::forgetGuards();
        $this->assertGuest();
        $this->browser($this->sessionIdFrom($response))->get('/profile')->assertRedirect('/login');
        $competingUser = User::query()->where('email', 'racing-client@example.com')->firstOrFail();
        $this->competingBrowser($competingSessionId, $competingUser, $competingOwnerToken)->assertOk();
    }

    public function test_customer_invitation_registration_does_not_authenticate_when_a_competing_claim_wins(): void
    {
        $barbershop = Barbershop::factory()->create();
        $issuer = User::factory()->create();
        $customer = Customer::factory()->for($barbershop)->create(['email' => 'invited-client@example.com']);
        $invitation = app(CustomerInvitationService::class)->issue($issuer, $barbershop, $customer, $customer->email);
        $competingSessionId = null;
        $competingOwnerToken = null;
        $this->listenForCompetingSessionClaim($competingSessionId, $competingOwnerToken);

        $response = $this->freshBrowser()->post(route('customer-invitations.register', $invitation['token'], false), [
            'name' => 'Clienta Invitada',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('errors');
        Auth::forgetGuards();
        $this->assertGuest();
        $this->browser($this->sessionIdFrom($response))->get('/profile')->assertRedirect('/login');
        $competingUser = User::query()->where('email', $customer->email)->firstOrFail();
        $this->competingBrowser($competingSessionId, $competingUser, $competingOwnerToken)->assertOk();
    }

    public function test_same_browser_can_continue_using_its_authenticated_session(): void
    {
        $user = $this->tenantUser();
        $sessionId = $this->loginAndGetSessionId($user);

        $this->browser($sessionId)->get('/profile')->assertOk();
    }

    public function test_normal_authenticated_request_renews_account_activity(): void
    {
        $user = $this->tenantUser();
        $sessionId = $this->loginAndGetSessionId($user);
        $previousActivity = $user->fresh()->active_session_at;
        $this->travel(1)->minutes();

        $this->browser($sessionId)->get('/profile')->assertOk();

        $this->assertTrue($user->fresh()->active_session_at->greaterThan($previousActivity));
    }

    public function test_registration_establishes_a_session_that_can_access_protected_account_pages(): void
    {
        $registration = $this->freshBrowser()->post('/register', [
            'name' => 'Nueva Clienta',
            'email' => 'nueva-clienta@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);
        $registration->assertForbidden();

        $this->get('/profile')->assertOk();
    }

    public function test_valid_remember_cookie_restores_the_current_owner_on_a_fresh_session(): void
    {
        $user = $this->tenantUser();
        $login = $this->login($user, remember: true);
        $rememberCookie = $login->getCookie($this->rememberCookieName());

        $response = $this->freshBrowser()->withCookie($this->rememberCookieName(), $rememberCookie->getValue())->get('/profile');

        $response->assertOk();
    }

    public function test_transfer_revokes_old_remember_cookie_and_issues_a_working_replacement(): void
    {
        $user = $this->tenantUser();
        $oldLogin = $this->login($user, remember: true);
        $oldRememberCookie = $oldLogin->getCookie($this->rememberCookieName());
        $conflict = $this->freshBrowser()->post('/login', $this->credentials($user, remember: true));
        $pendingSessionId = $this->sessionIdFrom($conflict);
        $transfer = $this->browser($pendingSessionId)->post('/login/session-conflict/confirm');
        $newRememberCookie = $transfer->getCookie($this->rememberCookieName());

        $this->freshBrowser()->withCookie($this->rememberCookieName(), $oldRememberCookie->getValue())->get('/profile')->assertRedirect('/login');
        $this->freshBrowser()->withCookie($this->rememberCookieName(), $newRememberCookie->getValue())->get('/profile')->assertOk();
    }

    public function test_cancelling_conflict_preserves_the_old_remember_cookie(): void
    {
        $user = $this->tenantUser();
        $oldLogin = $this->login($user, remember: true);
        $rememberCookie = $oldLogin->getCookie($this->rememberCookieName());
        $conflict = $this->freshBrowser()->post('/login', $this->credentials($user));

        $this->browser($this->sessionIdFrom($conflict))->post('/login/session-conflict/cancel')->assertRedirect('/login');
        $this->freshBrowser()->withCookie($this->rememberCookieName(), $rememberCookie->getValue())->get('/profile')->assertOk();
    }

    private function listenForCompetingSessionClaim(?string &$competingSessionId, ?string &$competingOwnerToken): void
    {
        Event::listen(Registered::class, function (Registered $event) use (&$competingSessionId, &$competingOwnerToken): void {
            $competingOwnerToken = Str::random(64);
            app(SingleDeviceSession::class)->claimAfterCredentials($event->user, $competingOwnerToken);
            $competingSessionId = Str::random(40);
        });
    }

    private function competingBrowser(?string $sessionId, User $user, ?string $ownerToken): TestResponse
    {
        $this->browser($sessionId);
        app('session')->driver()->setId($sessionId);

        return $this->withSession([
            Auth::guard('web')->getName() => $user->getAuthIdentifier(),
            SingleDeviceSession::OWNER_TOKEN_KEY => $ownerToken,
        ])->get('/profile');
    }

    private function tenantUser(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $barbershop = Barbershop::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create();

        return $user;
    }

    private function loginAndGetSessionId(User $user): string
    {
        return $this->sessionIdFrom($this->login($user));
    }

    private function login(User $user, bool $remember = false): TestResponse
    {
        $response = $this->freshBrowser()->post('/login', $this->credentials($user, $remember));
        $response->assertRedirect();

        return $response;
    }

    /** @return array{email: string, password: string, remember?: string} */
    private function credentials(User $user, bool $remember = false): array
    {
        return [
            'email' => $user->email,
            'password' => 'password',
            ...($remember ? ['remember' => 'on'] : []),
        ];
    }

    private function rememberCookieName(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    private function sessionIdFrom(TestResponse $response): string
    {
        return $response->getCookie(config('session.cookie'))->getValue();
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

    private function resetBrowserSessionBindings(): void
    {
        Auth::forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
    }
}
