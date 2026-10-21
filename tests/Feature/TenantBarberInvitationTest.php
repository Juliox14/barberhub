<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\BarberAccountInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class TenantBarberInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_new_account_invitation_and_existing_profile_forms(): void
    {
        [$barbershop, $owner] = $this->ownerContext();

        $this->actingAs($owner)
            ->get(route('tenant.barbers.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Invitar y crear cuenta nueva')
            ->assertSee('name="account_name"', false)
            ->assertSee('name="account_email"', false)
            ->assertSee('name="agenda_name"', false)
            ->assertSee('Crear perfil para miembro existente')
            ->assertSee('name="user_id"', false);
    }

    public function test_owner_can_create_a_new_barber_account_and_send_an_invitation(): void
    {
        Notification::fake();
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Centro']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->post(route('tenant.barbers.invitations.store', $barbershop, false), [
                'account_name' => 'Ana Navajas',
                'account_email' => 'ana@example.com',
                'agenda_name' => 'Ana Cortes',
            ])
            ->assertRedirect(route('tenant.barbers.index', $barbershop, false));

        $barberUser = User::query()->where('email', 'ana@example.com')->firstOrFail();

        $this->assertSame('Ana Navajas', $barberUser->name);
        $this->assertNull($barberUser->email_verified_at);
        $this->assertDatabaseHas('memberships', [
            'user_id' => $barberUser->id,
            'barbershop_id' => $barbershop->id,
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('barbers', [
            'user_id' => $barberUser->id,
            'barbershop_id' => $barbershop->id,
            'display_name' => 'Ana Cortes',
            'active' => true,
        ]);

        Notification::assertSentTo($barberUser, BarberAccountInvitation::class, function (BarberAccountInvitation $notification) use ($barberUser, $barbershop): bool {
            $this->assertSame($barbershop->name, $notification->barbershopName);
            $this->assertTrue(Password::broker()->tokenExists($barberUser, $notification->token));

            return true;
        });
    }

    public function test_invitation_link_sets_password_verifies_email_and_cannot_be_reused(): void
    {
        Notification::fake();
        [$barbershop, $owner] = $this->ownerContext();

        $this->actingAs($owner)
            ->post(route('tenant.barbers.invitations.store', $barbershop, false), [
                'account_name' => 'Luis Barbero',
                'account_email' => 'luis@example.com',
                'agenda_name' => 'Luis Cortes',
            ]);

        $barberUser = User::query()->where('email', 'luis@example.com')->firstOrFail();
        $token = null;
        Notification::assertSentTo($barberUser, BarberAccountInvitation::class, function (BarberAccountInvitation $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->actingAsGuest()
            ->get(route('barber-invitations.accept', ['token' => $token, 'email' => $barberUser->email], false))
            ->assertOk()
            ->assertSee('Configura tu contraseña');

        $payload = [
            'token' => $token,
            'email' => $barberUser->email,
            'password' => 'BarberHub-password-123!',
            'password_confirmation' => 'BarberHub-password-123!',
        ];

        $this->post(route('barber-invitations.complete', absolute: false), $payload)
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('status');

        $barberUser->refresh();
        $this->assertNotNull($barberUser->email_verified_at);
        $this->assertTrue(Hash::check('BarberHub-password-123!', $barberUser->password));

        $this->from(route('barber-invitations.accept', ['token' => $token, 'email' => $barberUser->email], false))
            ->post(route('barber-invitations.complete', absolute: false), $payload)
            ->assertForbidden();
    }

    public function test_expired_invitation_link_cannot_complete_account_setup(): void
    {
        Notification::fake();
        [$barbershop, $owner] = $this->ownerContext();

        $this->actingAs($owner)
            ->post(route('tenant.barbers.invitations.store', $barbershop, false), [
                'account_name' => 'Eva Navajas',
                'account_email' => 'eva@example.com',
                'agenda_name' => 'Eva Navajas',
            ]);

        $barberUser = User::query()->where('email', 'eva@example.com')->firstOrFail();
        $token = null;
        Notification::assertSentTo($barberUser, BarberAccountInvitation::class, function (BarberAccountInvitation $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->travel(config('auth.passwords.'.config('auth.defaults.passwords').'.expire') + 1)->minutes();

        $this->actingAsGuest()
            ->get(route('barber-invitations.accept', ['token' => $token, 'email' => $barberUser->email], false))
            ->assertForbidden();

        $this->post(route('barber-invitations.complete', absolute: false), [
            'token' => $token,
            'email' => $barberUser->email,
            'password' => 'BarberHub-password-123!',
            'password_confirmation' => 'BarberHub-password-123!',
        ])->assertSessionHasErrors('email');

        $this->assertNull($barberUser->fresh()->email_verified_at);
    }

    public function test_verified_barber_cannot_complete_invitation_with_password_reset_token(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barber = User::factory()->create(['email_verified_at' => '2025-01-01 12:00:00']);
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        Barber::factory()->for($barbershop)->create(['user_id' => $barber->id]);
        $originalAttributes = $barber->refresh()->getAttributes();
        $token = Password::broker()->createToken($barber);

        $this->post(route('barber-invitations.complete', absolute: false), [
            'email' => $barber->email,
            'token' => $token,
            'password' => 'BarberHub-password-123!',
            'password_confirmation' => 'BarberHub-password-123!',
        ])->assertForbidden();

        $this->assertSame($originalAttributes, $barber->fresh()->getAttributes());
        $this->assertTrue(Password::broker()->tokenExists($barber, $token));
    }

    public function test_barber_cannot_create_an_account_or_barber_profile(): void
    {
        Notification::fake();
        $barbershop = Barbershop::factory()->create();
        $barber = User::factory()->create();
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);

        $this->actingAs($barber)
            ->post(route('tenant.barbers.invitations.store', $barbershop, false), [
                'account_name' => 'Cuenta no autorizada',
                'account_email' => 'denied@example.com',
                'agenda_name' => 'No permitido',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'denied@example.com']);
        $this->assertDatabaseCount('barbers', 0);
        Notification::assertNothingSent();
    }

    public function test_owner_cannot_invite_duplicate_email_or_modify_existing_account(): void
    {
        Notification::fake();
        [$barbershop, $owner] = $this->ownerContext();
        $existingUser = User::factory()->create([
            'name' => 'Cuenta Existente',
            'email' => 'existing@example.com',
            'password' => 'existing-password',
        ]);
        $originalPassword = $existingUser->password;

        $this->actingAs($owner)
            ->from(route('tenant.barbers.index', $barbershop, false))
            ->post(route('tenant.barbers.invitations.store', $barbershop, false), [
                'account_name' => 'Intento de duplicado',
                'account_email' => 'existing@example.com',
                'agenda_name' => 'Otro nombre',
            ])
            ->assertRedirect(route('tenant.barbers.index', $barbershop, false))
            ->assertSessionHasErrors('account_email');

        $this->assertSame($originalPassword, $existingUser->fresh()->password);
        $this->assertDatabaseMissing('memberships', [
            'user_id' => $existingUser->id,
            'barbershop_id' => $barbershop->id,
        ]);
        $this->assertDatabaseMissing('barbers', [
            'user_id' => $existingUser->id,
            'barbershop_id' => $barbershop->id,
        ]);
        Notification::assertNothingSent();
    }

    public function test_user_without_tenant_membership_cannot_create_an_invited_account(): void
    {
        Notification::fake();
        $barbershop = Barbershop::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tenant.barbers.invitations.store', $barbershop, false), [
                'account_name' => 'Cuenta no autorizada',
                'account_email' => 'unassigned@example.com',
                'agenda_name' => 'Sin autorización',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unassigned@example.com']);
        Notification::assertNothingSent();
    }

    /**
     * @return array{0: Barbershop, 1: User}
     */
    private function ownerContext(): array
    {
        $barbershop = Barbershop::factory()->create(['name' => 'Barbería Centro']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        return [$barbershop, $owner];
    }
}
