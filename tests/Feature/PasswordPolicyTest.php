<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\User;
use App\Support\CustomerInvitationService;
use App\Support\PasswordPolicy;
use App\Support\SingleDeviceSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function invalidPasswords(): array
    {
        return [
            'too short' => ['Abcdefg123!'],
            'missing uppercase' => ['abcdefgh123!'],
            'missing lowercase' => ['ABCDEFGH123!'],
            'missing number' => ['Abcdefghijk!'],
            'missing symbol' => ['Abcdefgh1234'],
        ];
    }

    #[DataProvider('invalidPasswords')]
    public function test_registration_rejects_passwords_missing_a_complexity_requirement(string $password): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'password-policy@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'password-policy@example.com']);
    }

    public function test_expired_password_restricts_authenticated_requests_to_renewal(): void
    {
        $user = User::factory()->create([
            'password_changed_at' => now()->subDays(90),
        ]);

        $this->actingAs($user)
            ->get(route('password.renew'))
            ->assertOk()
            ->assertSee('Tu contraseña ha vencido')
            ->assertSee('al menos 12 caracteres');

        $this->get('/profile')->assertRedirect(route('password.renew'));
    }

    public function test_password_inside_ninety_day_lifecycle_remains_usable(): void
    {
        $user = User::factory()->create([
            'password_changed_at' => now()->subDays(89),
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk();
    }

    public function test_expired_password_can_be_renewed_without_ending_the_session(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['password_changed_at' => now()->subDays(90)])->saveQuietly();
        $ownerToken = str_repeat('a', 64);
        app(SingleDeviceSession::class)->claimAfterCredentials($user, $ownerToken);

        $response = $this->withSession([
            app('auth')->guard('web')->getName() => $user->getAuthIdentifier(),
            SingleDeviceSession::OWNER_TOKEN_KEY => $ownerToken,
        ])->actingAs($user)
            ->from(route('password.renew'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'RenewedPassword123!',
                'password_confirmation' => 'RenewedPassword123!',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('password.renew'));
        $this->assertAuthenticatedAs($user);
        $this->assertFalse(PasswordPolicy::expired($user->fresh()));
        $this->assertNotNull($user->fresh()->active_session_id);
    }

    public function test_missing_password_timestamp_fails_closed(): void
    {
        $user = User::factory()->make();

        $this->assertTrue(PasswordPolicy::expired($user));
    }

    public function test_non_password_account_updates_do_not_extend_the_password_lifecycle(): void
    {
        $user = User::factory()->create([
            'password_changed_at' => now()->subDays(30),
        ]);
        $originalTimestamp = $user->password_changed_at->toDateTimeString();

        $user->update(['name' => 'Updated Name']);

        $this->assertSame($originalTimestamp, $user->fresh()->password_changed_at->toDateTimeString());
    }

    public function test_session_status_reports_expired_password_without_ending_session(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['password_changed_at' => now()->subDays(90)])->saveQuietly();

        app(SingleDeviceSession::class)->claimAfterCredentials($user, 'current-owner');
        $this->actingAs($user)->withSession([
            app('auth')->guard('web')->getName() => $user->id,
            SingleDeviceSession::OWNER_TOKEN_KEY => 'current-owner',
        ])->getJson(route('session.status'))
            ->assertUnauthorized()
            ->assertJsonPath('status', 'password_expired');

        $this->assertAuthenticatedAs($user);
    }

    /** @return array<string, array{string, ?string}> */
    public static function resetAndInvitationEndpoints(): array
    {
        return [
            'password recovery' => ['/reset-password', null],
            'owner invitation' => ['/owner-invitations', 'owner'],
            'barber invitation' => ['/barber-invitations', 'barber'],
        ];
    }

    #[DataProvider('resetAndInvitationEndpoints')]
    public function test_weak_password_rejection_preserves_the_account_and_token_for_a_valid_retry(string $endpoint, ?string $role): void
    {
        $user = User::factory()->unverified()->create();
        if ($role !== null) {
            $shop = Barbershop::factory()->create();
            Membership::factory()->for($user)->for($shop)->create(['role' => $role]);
            if ($role === 'barber') {
                Barber::factory()->for($user)->for($shop)->create(['active' => true]);
            }
        }
        $token = Password::broker()->createToken($user);
        $before = $user->fresh()->getRawOriginal();
        $auditCount = DB::table('security_audit_logs')->count();
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'weak-password', 'password_confirmation' => 'weak-password'];

        $this->post($endpoint, $payload)->assertSessionHasErrors('password');
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertSame($auditCount, DB::table('security_audit_logs')->count());
        $this->post($endpoint, [...$payload, 'password' => 'Replacement123!', 'password_confirmation' => 'Replacement123!'])
            ->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('Replacement123!', $user->fresh()->password));
    }

    public function test_weak_password_cannot_consume_a_customer_invitation_or_create_an_account(): void
    {
        $shop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($shop)->create(['role' => 'owner']);
        $customer = Customer::factory()->for($shop)->create(['email' => 'invited@example.com']);
        $issued = app(CustomerInvitationService::class)->issue($owner, $shop, $customer, 'invited@example.com');
        $before = $issued['invitation']->fresh()->getRawOriginal();
        $payload = ['name' => 'Invited Person', 'password' => 'weak-password', 'password_confirmation' => 'weak-password'];
        $url = '/customer-invitations/'.$issued['token'].'/register';

        $this->post($url, $payload)->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 1);
        $this->assertNull($customer->fresh()->user_id);
        $this->assertSame($before, $issued['invitation']->fresh()->getRawOriginal());
        $this->post($url, [...$payload, 'password' => 'Replacement123!', 'password_confirmation' => 'Replacement123!'])
            ->assertSessionHasNoErrors()->assertRedirect('/');
        $this->assertDatabaseCount('users', 2);
        $this->assertNotNull($customer->fresh()->user_id);
    }

    public function test_weak_password_update_cannot_extend_the_expiry_deadline(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()->subDays(90)]);
        $before = $user->fresh()->getRawOriginal();
        $this->actingAs($user)->put('/password', [
            'current_password' => 'password', 'password' => 'weak-password', 'password_confirmation' => 'weak-password',
        ])->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertDatabaseMissing('security_audit_logs', ['event' => 'password.updated']);
    }

    public function test_database_default_activates_existing_accounts_at_migration_time(): void
    {
        DB::table('users')->insert([
            'name' => 'Existing Account',
            'email' => 'existing-account@example.com',
            'password' => bcrypt('password'),
            'created_at' => '2000-01-01 00:00:00',
            'updated_at' => '2000-01-01 00:00:00',
        ]);

        $timestamp = DB::table('users')
            ->where('email', 'existing-account@example.com')
            ->value('password_changed_at');

        $this->assertNotNull($timestamp);
        $this->assertNotSame('2000-01-01 00:00:00', $timestamp);
    }
}
