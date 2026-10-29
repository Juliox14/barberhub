<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\OwnerAccountInvitation;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class OwnerAccountInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
    }

    public function test_platform_admin_can_invite_new_owner_to_create_barbershop_and_owner_can_set_password(): void
    {
        Mail::fake();
        Notification::fake();
        Event::fake([PasswordReset::class]);
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $response = $this->actingAs($platformAdmin)
            ->post(route('platform.barbershops.store', absolute: false), $this->newOwnerPayload());

        $barbershop = Barbershop::query()->where('slug', 'barberia-invitada')->firstOrFail();
        $owner = User::query()->where('email', 'nueva-duena@example.com')->firstOrFail();

        $response->assertRedirect(route('platform.barbershops.index', absolute: false));
        $this->assertNull($owner->email_verified_at);
        $this->assertSame('Dueña Invitada', $owner->name);
        $this->assertSame('client', $owner->role);
        $this->assertFalse($owner->is_platform_admin);
        $this->assertNotSame('', $owner->password);
        $this->assertFalse(Hash::check('contrasena-provisional', $owner->password));
        $this->assertDatabaseHas('memberships', [
            'user_id' => $owner->id,
            'barbershop_id' => $barbershop->id,
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
        ]);
        Notification::assertSentTo($owner, OwnerAccountInvitation::class);
        $invitation = Notification::sent($owner, OwnerAccountInvitation::class)->first();
        $this->assertInstanceOf(OwnerAccountInvitation::class, $invitation);
        $token = $invitation->token;
        $this->assertTrue(Password::broker()->tokenExists($owner, $token));
        auth()->logout();
        $this->get(route('owner-invitations.accept', [
            'token' => $token,
            'email' => $owner->email,
        ], false))->assertOk()->assertSee($owner->email);

        $this->post(route('owner-invitations.complete', absolute: false), [
            'email' => $owner->email,
            'token' => $token,
            'password' => 'NuevaContrasena123!',
            'password_confirmation' => 'NuevaContrasena123!',
        ])->assertRedirect(route('login', absolute: false));

        $owner->refresh();
        $this->assertNotNull($owner->email_verified_at);
        $this->assertTrue(Hash::check('NuevaContrasena123!', $owner->password));
        Event::assertDispatched(PasswordReset::class);
        $this->post(route('login', absolute: false), [
            'email' => $owner->email,
            'password' => 'NuevaContrasena123!',
        ])->assertRedirect();
    }

    public function test_invalid_expired_and_replayed_owner_invitation_tokens_cannot_set_password(): void
    {
        Mail::fake();
        Notification::fake();
        $owner = $this->createInvitedOwner();

        $this->post(route('owner-invitations.complete', absolute: false), [
            'email' => $owner->email,
            'token' => 'invalid-token',
            'password' => 'NuevaContrasena123!',
            'password_confirmation' => 'NuevaContrasena123!',
        ])->assertSessionHasErrors('email');
        $this->assertFalse(Hash::check('NuevaContrasena123!', $owner->fresh()->password));

        $expiredToken = Password::broker()->createToken($owner);
        $this->travel(61)->minutes();
        $this->post(route('owner-invitations.complete', absolute: false), [
            'email' => $owner->email,
            'token' => $expiredToken,
            'password' => 'NuevaContrasena123!',
            'password_confirmation' => 'NuevaContrasena123!',
        ])->assertSessionHasErrors('email');
        $this->assertNull($owner->fresh()->email_verified_at);

        $this->travelBack();
        $token = Password::broker()->createToken($owner);
        $credentials = [
            'email' => $owner->email,
            'token' => $token,
            'password' => 'NuevaContrasena123!',
            'password_confirmation' => 'NuevaContrasena123!',
        ];
        $this->post(route('owner-invitations.complete', absolute: false), $credentials)->assertRedirect(route('login', absolute: false));
        $this->post(route('owner-invitations.complete', absolute: false), $credentials)->assertForbidden();
        Notification::assertSentTo($owner, OwnerAccountInvitation::class);
    }

    public function test_verified_or_non_owner_accounts_are_ineligible_to_complete_owner_invitation(): void
    {
        Mail::fake();
        Notification::fake();
        $verifiedOwner = $this->createInvitedOwner();
        $verifiedOwner->forceFill(['email_verified_at' => now()])->save();
        auth()->logout();
        $verifiedToken = Password::broker()->createToken($verifiedOwner);
        $this->get(route('owner-invitations.accept', ['token' => $verifiedToken, 'email' => $verifiedOwner->email], false))
            ->assertForbidden();

        $barber = User::factory()->create(['email_verified_at' => null]);
        $shop = Barbershop::factory()->create();
        Membership::factory()->create([
            'user_id' => $barber->id,
            'barbershop_id' => $shop->id,
            'role' => Membership::ROLE_BARBER,
            'status' => Membership::STATUS_ACTIVE,
        ]);
        $barberToken = Password::broker()->createToken($barber);
        $this->post(route('owner-invitations.complete', absolute: false), [
            'email' => $barber->email,
            'token' => $barberToken,
            'password' => 'NuevaContrasena123!',
            'password_confirmation' => 'NuevaContrasena123!',
        ])->assertForbidden();

        $this->assertNull($barber->fresh()->email_verified_at);
        $this->assertFalse(Hash::check('NuevaContrasena123!', $barber->fresh()->password));
    }

    public function test_duplicate_email_is_rejected_without_modifying_existing_account(): void
    {
        Mail::fake();
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $existing = User::factory()->create([
            'name' => 'Cuenta existente',
            'email' => 'nueva-duena@example.com',
            'password' => Hash::make('ClaveOriginal123!'),
        ]);

        $this->actingAs($platformAdmin)
            ->from(route('platform.barbershops.create', absolute: false))
            ->post(route('platform.barbershops.store', absolute: false), $this->newOwnerPayload())
            ->assertRedirect(route('platform.barbershops.create', absolute: false))
            ->assertSessionHasErrors('owner_email');

        $this->assertSame('Cuenta existente', $existing->fresh()->name);
        $this->assertTrue(Hash::check('ClaveOriginal123!', $existing->fresh()->password));
        $this->assertSame($existing->email_verified_at->toDateTimeString(), $existing->fresh()->email_verified_at->toDateTimeString());
        $this->assertDatabaseMissing('barbershops', ['slug' => 'barberia-invitada']);
        $this->assertDatabaseMissing('memberships', ['user_id' => $existing->id]);
        Mail::assertNothingOutgoing();
    }

    public function test_invalid_barbershop_or_owner_fields_leave_no_records_or_email(): void
    {
        Mail::fake();
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        $payload = $this->newOwnerPayload();
        $payload['timezone'] = 'Europe/Madrid';
        unset($payload['owner_name']);

        $this->actingAs($platformAdmin)
            ->from(route('platform.barbershops.create', absolute: false))
            ->post(route('platform.barbershops.store', absolute: false), $payload)
            ->assertRedirect(route('platform.barbershops.create', absolute: false))
            ->assertSessionHasErrors(['timezone', 'owner_name']);

        $this->assertDatabaseMissing('barbershops', ['slug' => 'barberia-invitada']);
        $this->assertDatabaseMissing('users', ['email' => 'nueva-duena@example.com']);
        $this->assertDatabaseCount('memberships', 0);
        Mail::assertNothingOutgoing();
    }

    /** @param array<string, mixed> $attributes */
    private function createInvitedOwner(array $attributes = []): User
    {
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($platformAdmin)->post(
            route('platform.barbershops.store', absolute: false),
            array_replace($this->newOwnerPayload(), $attributes),
        );
        auth()->logout();

        return User::query()->where('email', $attributes['email'] ?? 'nueva-duena@example.com')->firstOrFail();
    }

    /** @return array<string, string> */
    private function newOwnerPayload(): array
    {
        return [
            'name' => 'Barbería Invitada',
            'slug' => 'barberia-invitada',
            'timezone' => 'America/Bogota',
            'owner_type' => 'new',
            'owner_name' => 'Dueña Invitada',
            'owner_email' => 'nueva-duena@example.com',
        ];
    }
}
