<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\CustomerInvitation;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_issue_a_personal_invitation_without_changing_customer_or_appointment_history(): void
    {
        $this->freezeTime();
        [$barbershop, $owner] = $this->ownerContext();
        $customer = Customer::factory()->for($barbershop)->create(['email' => 'client@example.com']);
        $appointment = $this->appointmentFor($barbershop, $customer);

        $response = $this->actingAs($owner)->post($this->issueUrl($barbershop, $customer), [
            'email' => 'client@example.com',
        ]);

        $response->assertRedirect(route('tenant.appointments.index', $barbershop, false));
        $response->assertSessionHas('invitation_url');
        $response->assertSessionHas('status', 'Enlace personal creado. Compártelo con client@example.com antes de 48 horas.');
        $customer->refresh();
        $this->assertNull($customer->user_id);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'customer_id' => $customer->id]);
        $this->assertSame(1, $barbershop->customers()->count());
        $invitation = CustomerInvitation::query()->firstOrFail();
        $this->assertSame(now()->addHours(48)->toDateTimeString(), $invitation->expires_at->toDateTimeString());
        $invitationUrl = $response->getSession()->get('invitation_url');
        $this->assertSame(hash('sha256', basename($invitationUrl)), $invitation->token_hash);
    }

    public function test_barber_can_issue_only_for_a_customer_in_their_own_appointment_history(): void
    {
        $barbershop = Barbershop::factory()->create();
        [$barberUser, $barber] = $this->barberContext($barbershop);
        $ownedCustomer = Customer::factory()->for($barbershop)->create();
        $otherCustomer = Customer::factory()->for($barbershop)->create();
        $this->appointmentFor($barbershop, $ownedCustomer, $barber);
        $this->appointmentFor($barbershop, $otherCustomer);

        $this->actingAs($barberUser)
            ->post($this->issueUrl($barbershop, $ownedCustomer), ['email' => 'owned@example.com'])
            ->assertRedirect(route('tenant.appointments.index', $barbershop, false));

        $this->actingAs($barberUser)
            ->post($this->issueUrl($barbershop, $otherCustomer), ['email' => 'other@example.com'])
            ->assertForbidden();

        $this->assertDatabaseCount('customer_invitations', 1);
    }

    public function test_issuer_cannot_access_another_tenant_customer_or_issue_for_an_unrelated_tenant(): void
    {
        [$barbershop, $owner] = $this->ownerContext();
        $otherBarbershop = Barbershop::factory()->create();
        $foreignCustomer = Customer::factory()->for($otherBarbershop)->create();

        $this->actingAs($owner)
            ->post($this->issueUrl($barbershop, $foreignCustomer), ['email' => 'foreign@example.com'])
            ->assertNotFound();

        $this->actingAs($owner)
            ->post($this->issueUrl($otherBarbershop, $foreignCustomer), ['email' => 'foreign@example.com'])
            ->assertForbidden();

        $this->assertDatabaseCount('customer_invitations', 0);
        $this->assertNull($foreignCustomer->fresh()->user_id);
    }

    public function test_active_tenant_administrator_can_issue_a_customer_invitation(): void
    {
        $barbershop = Barbershop::factory()->create();
        $administrator = User::factory()->create();
        Membership::factory()->for($administrator)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);
        $customer = Customer::factory()->for($barbershop)->create();

        $this->actingAs($administrator)
            ->post($this->issueUrl($barbershop, $customer), ['email' => 'admin-invite@example.com'])
            ->assertRedirect(route('tenant.appointments.index', $barbershop, false));

        $this->assertDatabaseHas('customer_invitations', [
            'barbershop_id' => $barbershop->id,
            'customer_id' => $customer->id,
            'issued_by' => $administrator->id,
            'email' => 'admin-invite@example.com',
        ]);
    }

    public function test_unauthenticated_issuer_is_redirected_to_login(): void
    {
        [$barbershop] = $this->ownerContext();
        $customer = Customer::factory()->for($barbershop)->create();

        $this->post($this->issueUrl($barbershop, $customer), ['email' => 'client@example.com'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('customer_invitations', 0);
    }

    public function test_existing_account_login_returns_to_confirmation_and_explicit_acceptance_preserves_history(): void
    {
        [$barbershop, $owner] = $this->ownerContext();
        $customer = Customer::factory()->for($barbershop)->create(['email' => 'client@example.com']);
        $appointment = $this->appointmentFor($barbershop, $customer);
        $invitationUrl = $this->issueInvitation($owner, $barbershop, $customer);
        $token = basename($invitationUrl);
        $user = User::factory()->create(['email' => 'client@example.com', 'password' => 'customer-password']);

        $this->actingAsGuest()->get($invitationUrl)->assertRedirect(route('login'));
        $this->post(route('login', absolute: false), [
            'email' => $user->email,
            'password' => 'customer-password',
        ])->assertRedirect($invitationUrl);

        $this->get($invitationUrl)->assertOk()->assertSee('Confirmar vinculación');
        $this->assertNull($customer->fresh()->user_id);
        $this->assertNull(CustomerInvitation::query()->firstOrFail()->consumed_at);

        $this->post($invitationUrl.'/accept')
            ->assertRedirect('/')
            ->assertSessionHas('status', 'Tu cuenta quedó vinculada con el historial de '.$customer->name.'.');

        $this->assertSame($user->id, $customer->fresh()->user_id);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'customer_id' => $customer->id]);
        $this->assertSame(1, $customer->appointments()->count());
        $this->post($invitationUrl.'/accept')->assertGone();
        $this->assertSame($user->id, $customer->fresh()->user_id);
        $this->assertSame(64, strlen($token));
    }

    public function test_guest_can_open_a_prefilled_invitation_form_without_writing_data(): void
    {
        [$barbershop, $owner] = $this->ownerContext();
        $customer = Customer::factory()->for($barbershop)->create([
            'name' => 'Cliente Original',
            'phone' => '555-0142',
            'email' => 'newclient@example.com',
        ]);
        $invitationUrl = $this->issueInvitation($owner, $barbershop, $customer);
        $customerBefore = $customer->fresh()->getAttributes();
        $invitationBefore = CustomerInvitation::query()->firstOrFail()->getAttributes();
        $usersBefore = User::query()->count();

        $this->actingAsGuest()->get($invitationUrl)
            ->assertOk()
            ->assertSee('value="Cliente Original"', false)
            ->assertSee('value="555-0142"', false)
            ->assertSee('newclient@example.com')
            ->assertDontSee('name="email"', false)
            ->assertSee('Crear cuenta y vincular');

        $this->assertGuest();
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($customerBefore, $customer->fresh()->getAttributes());
        $this->assertSame($invitationBefore, CustomerInvitation::query()->firstOrFail()->getAttributes());
    }

    public function test_guest_can_create_and_link_account_with_edited_customer_details_and_fixed_invitation_identity(): void
    {
        [$barbershop, $owner] = $this->ownerContext();
        $customer = Customer::factory()->for($barbershop)->create([
            'name' => 'Nombre anterior',
            'phone' => '555-0101',
            'email' => 'old@example.com',
        ]);
        $appointment = $this->appointmentFor($barbershop, $customer);
        $invitationUrl = $this->issueInvitation($owner, $barbershop, $customer);
        $this->actingAsGuest();
        $sessionIdBefore = $this->app['session']->getId();
        $token = basename($invitationUrl);

        $this->post($invitationUrl.'/register', [
            'name' => 'Nombre actualizado',
            'phone' => '555-0199',
            'email' => 'attacker@example.com',
            'role' => 'owner',
            'is_platform_admin' => true,
            'email_verified_at' => now()->toDateTimeString(),
            'password' => 'CustomerPassword123!',
            'password_confirmation' => 'CustomerPassword123!',
        ])->assertRedirect('/')->assertSessionHas('status');

        $user = User::query()->where('email', 'old@example.com')->firstOrFail();
        $customer->refresh();
        $invitation = CustomerInvitation::query()->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionIdBefore, $this->app['session']->getId());
        $this->assertSame('client', $user->role);
        $this->assertTrue(Hash::check('CustomerPassword123!', $user->password));
        $this->assertFalse($user->is_platform_admin);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('Nombre actualizado', $user->name);
        $this->assertSame('Nombre actualizado', $customer->name);
        $this->assertSame('555-0199', $customer->phone);
        $this->assertSame('old@example.com', $customer->email);
        $this->assertSame($user->id, $customer->user_id);
        $this->assertSame(now()->toDateTimeString(), $invitation->consumed_at->toDateTimeString());
        $this->assertSame($user->id, $invitation->accepted_by);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'customer_id' => $customer->id]);
        $this->assertSame(64, strlen($token));

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->post($invitationUrl.'/register', [
            'name' => 'Otra cuenta',
            'phone' => '555-0000',
            'password' => 'CustomerPassword123!',
            'password_confirmation' => 'CustomerPassword123!',
        ])->assertGone();
        $this->assertSame('Nombre actualizado', $customer->fresh()->name);
        $this->assertSame(1, User::query()->where('email', 'old@example.com')->count());
    }

    public function test_authenticated_different_user_cannot_create_invited_account_or_be_switched(): void
    {
        [$barbershop, $owner] = $this->ownerContext();
        $customer = Customer::factory()->for($barbershop)->create([
            'name' => 'Cliente objetivo',
            'phone' => '555-0111',
            'email' => 'target@example.com',
        ]);
        $invitationUrl = $this->issueInvitation($owner, $barbershop, $customer);
        $otherUser = User::factory()->create(['email' => 'other@example.com']);
        $usersBefore = User::query()->count();
        $this->actingAs($otherUser);
        $invitation = CustomerInvitation::query()->firstOrFail();

        $response = $this->post($invitationUrl.'/register', [
            'name' => 'Cuenta no autorizada',
            'phone' => '555-0999',
            'password' => 'customer-password',
            'password_confirmation' => 'customer-password',
        ]);
        $customer->refresh();
        $invitation->refresh();

        $this->assertSame([
            'status' => 403,
            'authenticated_user_id' => $otherUser->id,
            'user_count' => $usersBefore,
            'customer_user_id' => null,
            'customer_name' => 'Cliente objetivo',
            'customer_phone' => '555-0111',
            'customer_email' => 'target@example.com',
            'invitation_consumed_at' => null,
            'invitation_accepted_by' => null,
        ], [
            'status' => $response->getStatusCode(),
            'authenticated_user_id' => auth()->id(),
            'user_count' => User::query()->count(),
            'customer_user_id' => $customer->user_id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'customer_email' => $customer->email,
            'invitation_consumed_at' => $invitation->consumed_at,
            'invitation_accepted_by' => $invitation->accepted_by,
        ]);
    }

    public function test_invitation_onboarding_clears_phone_when_the_submitted_value_is_blank(): void
    {
        [$barbershop, $owner] = $this->ownerContext();
        $customer = Customer::factory()->for($barbershop)->create([
            'phone' => '555-0155',
            'email' => 'clear-phone@example.com',
        ]);
        $invitationUrl = $this->issueInvitation($owner, $barbershop, $customer);

        $this->actingAsGuest()->post($invitationUrl.'/register', [
            'name' => $customer->name,
            'phone' => '',
            'password' => 'CustomerPassword123!',
            'password_confirmation' => 'CustomerPassword123!',
        ])->assertRedirect('/');

        $this->assertNull($customer->fresh()->phone);
    }

    public function test_invalid_invitation_onboarding_can_be_corrected_without_consuming_or_writing(): void
    {
        [$barbershop, $owner] = $this->ownerContext();
        $customer = Customer::factory()->for($barbershop)->create(['email' => 'target@example.com']);
        $invitationUrl = $this->issueInvitation($owner, $barbershop, $customer);
        $customerBefore = $customer->fresh()->getAttributes();
        $invitationBefore = CustomerInvitation::query()->firstOrFail()->getAttributes();
        $usersBefore = User::query()->count();
        $this->actingAsGuest();

        $this->post($invitationUrl.'/register', [
            'name' => 'Editar nombre',
            'phone' => '555-0200',
            'password' => 'CustomerPassword123!',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($customerBefore, $customer->fresh()->getAttributes());
        $this->assertSame($invitationBefore, CustomerInvitation::query()->firstOrFail()->getAttributes());

        $this->post($invitationUrl.'/register', [
            'name' => 'Editar nombre',
            'phone' => '555-0200',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($customerBefore, $customer->fresh()->getAttributes());
        $this->assertSame($invitationBefore, CustomerInvitation::query()->firstOrFail()->getAttributes());

        $this->post($invitationUrl.'/register', [
            'name' => 'Nombre corregido',
            'phone' => '555-0200',
            'password' => 'CustomerPassword123!',
            'password_confirmation' => 'CustomerPassword123!',
        ])->assertRedirect('/');
        $this->assertSame('Nombre corregido', $customer->fresh()->name);
        $this->assertSame(1, User::query()->where('email', 'target@example.com')->count());
    }

    public function test_wrong_logged_in_email_cannot_accept_or_consume_invitation(): void
    {
        [$barbershop, $owner] = $this->ownerContext();
        $customer = Customer::factory()->for($barbershop)->create(['email' => 'target@example.com']);
        $invitationUrl = $this->issueInvitation($owner, $barbershop, $customer);
        $otherUser = User::factory()->create(['email' => 'other@example.com']);
        $originalAttributes = $customer->fresh()->getAttributes();

        $this->actingAs($otherUser)->get($invitationUrl)->assertForbidden();
        $this->actingAs($otherUser)->post($invitationUrl.'/accept')->assertForbidden();

        $this->assertSame($originalAttributes, $customer->fresh()->getAttributes());
        $this->assertNull(CustomerInvitation::query()->firstOrFail()->consumed_at);
    }

    public function test_expired_invitation_cannot_be_viewed_or_accepted_and_does_not_change_customer(): void
    {
        [$barbershop, $owner] = $this->ownerContext();
        $customer = Customer::factory()->for($barbershop)->create(['email' => 'expired@example.com']);
        $invitationUrl = $this->issueInvitation($owner, $barbershop, $customer);
        $user = User::factory()->create(['email' => 'expired@example.com']);
        $before = $customer->fresh()->getAttributes();
        $this->travel(49)->hours();

        $this->actingAs($user)->get($invitationUrl)->assertGone();
        $this->actingAs($user)->post($invitationUrl.'/accept')->assertGone();

        $this->assertSame($before, $customer->fresh()->getAttributes());
        $this->assertDatabaseCount('customer_invitations', 1);
        $this->assertNull(CustomerInvitation::query()->firstOrFail()->consumed_at);
    }

    public function test_already_linked_customer_identity_cannot_be_replaced_or_consume_invitation(): void
    {
        [$barbershop, $owner] = $this->ownerContext();
        $originalUser = User::factory()->create(['email' => 'original@example.com']);
        $customer = Customer::factory()->for($barbershop)->create([
            'user_id' => $originalUser->id,
            'email' => 'target@example.com',
        ]);
        $invitationUrl = $this->issueInvitation($owner, $barbershop, $customer);
        $targetUser = User::factory()->create(['email' => 'target@example.com']);
        $before = $customer->fresh()->getAttributes();

        $this->actingAs($targetUser)->get($invitationUrl)->assertConflict();
        $this->actingAs($targetUser)->post($invitationUrl.'/accept')->assertConflict();

        $this->assertSame($before, $customer->fresh()->getAttributes());
        $this->assertSame($originalUser->id, $customer->fresh()->user_id);
        $this->assertNull(CustomerInvitation::query()->firstOrFail()->consumed_at);
    }

    public function test_existing_account_collision_after_guest_form_open_preserves_account_and_requires_login_and_confirmation(): void
    {
        [$barbershop, $owner] = $this->ownerContext();
        $customer = Customer::factory()->for($barbershop)->create(['email' => 'target@example.com']);
        $appointment = $this->appointmentFor($barbershop, $customer);
        $invitationUrl = $this->issueInvitation($owner, $barbershop, $customer);
        $this->actingAsGuest()->get($invitationUrl)->assertOk();
        $existingUser = User::factory()->create(['email' => 'target@example.com', 'password' => 'existing-password']);
        $passwordHash = $existingUser->getAuthPassword();
        $customerBefore = $customer->fresh()->getAttributes();
        $invitationBefore = CustomerInvitation::query()->firstOrFail()->getAttributes();

        $this->post($invitationUrl.'/register', [
            'name' => 'No reemplazar',
            'phone' => '555-0300',
            'password' => 'CustomerPassword123!',
            'password_confirmation' => 'CustomerPassword123!',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame($passwordHash, $existingUser->fresh()->getAuthPassword());
        $this->assertSame(1, User::query()->where('email', 'target@example.com')->count());
        $this->assertSame($customerBefore, $customer->fresh()->getAttributes());
        $this->assertSame($invitationBefore, CustomerInvitation::query()->firstOrFail()->getAttributes());
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'customer_id' => $customer->id]);

        $this->post(route('login', absolute: false), [
            'email' => 'target@example.com',
            'password' => 'existing-password',
        ])->assertRedirect($invitationUrl);
        $this->get($invitationUrl)->assertOk()->assertSee('Confirmar vinculación');
        $this->assertNull($customer->fresh()->user_id);
        $this->assertNull(CustomerInvitation::query()->firstOrFail()->consumed_at);

        $this->post($invitationUrl.'/accept')->assertRedirect('/');
        $this->assertSame($existingUser->id, $customer->fresh()->user_id);
        $this->assertSame($passwordHash, $existingUser->fresh()->getAuthPassword());
    }

    public function test_explicit_linkage_keeps_separate_customer_records_across_barbershops(): void
    {
        $existingUser = User::factory()->create(['email' => 'client@example.com']);
        $firstBarbershop = Barbershop::factory()->create();
        $firstCustomer = Customer::factory()->for($firstBarbershop)->create(['user_id' => $existingUser->id]);
        $firstAppointment = $this->appointmentFor($firstBarbershop, $firstCustomer);
        [$secondBarbershop, $owner] = $this->ownerContext();
        $secondCustomer = Customer::factory()->for($secondBarbershop)->create([
            'name' => $firstCustomer->name,
            'email' => 'client@example.com',
        ]);
        $secondAppointment = $this->appointmentFor($secondBarbershop, $secondCustomer);
        $invitationUrl = $this->issueInvitation($owner, $secondBarbershop, $secondCustomer);

        $this->actingAs($existingUser)
            ->get($invitationUrl)
            ->assertOk()
            ->assertSee('Confirmar vinculación');
        $this->assertNull($secondCustomer->fresh()->user_id);
        $this->actingAs($existingUser)->post($invitationUrl.'/accept')->assertRedirect('/');

        $this->assertSame($existingUser->id, $firstCustomer->fresh()->user_id);
        $this->assertSame($existingUser->id, $secondCustomer->fresh()->user_id);
        $this->assertDatabaseHas('appointments', ['id' => $firstAppointment->id, 'customer_id' => $firstCustomer->id]);
        $this->assertDatabaseHas('appointments', ['id' => $secondAppointment->id, 'customer_id' => $secondCustomer->id]);
        $this->assertSame(2, $existingUser->fresh()->customers()->count());
    }

    public function test_invitation_form_is_available_in_the_spanish_appointment_agenda(): void
    {
        [$barbershop, $owner] = $this->ownerContext();
        $customer = Customer::factory()->for($barbershop)->create(['email' => 'client@example.com']);

        $this->actingAs($owner)
            ->get(route('tenant.appointments.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Crear enlace para el cliente')
            ->assertSee($this->issueUrl($barbershop, $customer), false);
    }

    private function issueInvitation(User $issuer, Barbershop $barbershop, Customer $customer): string
    {
        $response = $this->actingAs($issuer)->post($this->issueUrl($barbershop, $customer), [
            'email' => $customer->email,
        ]);

        $response->assertRedirect(route('tenant.appointments.index', $barbershop, false));

        return (string) $response->getSession()->get('invitation_url');
    }

    private function issueUrl(Barbershop $barbershop, Customer $customer): string
    {
        return '/barbershops/'.$barbershop->slug.'/customers/'.$customer->id.'/invitations';
    }

    /** @return array{0: Barbershop, 1: User} */
    private function ownerContext(): array
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        return [$barbershop, $owner];
    }

    /** @return array{0: User, 1: Barber} */
    private function barberContext(Barbershop $barbershop): array
    {
        $barberUser = User::factory()->create();
        Membership::factory()->for($barberUser)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        $barber = Barber::factory()->for($barbershop)->create(['user_id' => $barberUser->id]);

        return [$barberUser, $barber];
    }

    private function appointmentFor(Barbershop $barbershop, Customer $customer, ?Barber $barber = null): Appointment
    {
        $barber ??= Barber::factory()->for($barbershop)->create();
        $service = Service::factory()->for($barbershop)->create();

        return Appointment::factory()->for($barbershop)->create([
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'customer_id' => $customer->id,
        ]);
    }
}
