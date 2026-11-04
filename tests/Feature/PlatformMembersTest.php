<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_view_owners_barbers_and_customers(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create();
        $owner = User::factory()->create(['name' => 'Dueña Central']);
        $barber = User::factory()->create(['name' => 'Barbero Central']);
        Membership::factory()->create(['barbershop_id' => $shop->id, 'user_id' => $owner->id, 'role' => Membership::ROLE_OWNER]);
        Membership::factory()->create(['barbershop_id' => $shop->id, 'user_id' => $barber->id, 'role' => Membership::ROLE_BARBER, 'status' => Membership::STATUS_INACTIVE]);
        $customer = Customer::factory()->create(['barbershop_id' => $shop->id, 'name' => 'Cliente sin cuenta', 'user_id' => null, 'email' => 'cliente@example.com']);

        $this->actingAs($admin)->get('/platform/members')
            ->assertOk()
            ->assertSee($shop->name)
            ->assertSee($owner->name)
            ->assertSee($owner->email)
            ->assertSee('Dueño')
            ->assertSee($barber->name)
            ->assertSee('Barbero')
            ->assertSee('Inactivo')
            ->assertSee($customer->name)
            ->assertSee($customer->email);
    }

    public function test_members_can_be_filtered_by_barbershop(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create();
        $member = User::factory()->create(['name' => 'Miembro seleccionado']);
        Membership::factory()->create(['barbershop_id' => $shop->id, 'user_id' => $member->id]);
        $customer = Customer::factory()->create(['barbershop_id' => $shop->id, 'name' => 'Cliente seleccionado']);
        $otherMember = User::factory()->create(['name' => 'Miembro ajeno']);
        Membership::factory()->create(['user_id' => $otherMember->id]);
        $otherCustomer = Customer::factory()->create(['name' => 'Cliente ajeno']);

        $this->actingAs($admin)->get('/platform/members?barbershop='.$shop->slug)
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee($customer->name)
            ->assertDontSee($otherMember->name)
            ->assertDontSee($otherCustomer->name);

        $this->get('/platform/members?barbershop=missing-shop')->assertNotFound();
    }

    public function test_members_page_requires_platform_admin(): void
    {
        $this->get('/platform/members')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_platform_admin' => false]))
            ->get('/platform/members')->assertForbidden();
    }

    public function test_platform_navigation_has_members_instead_of_upcoming_modules(): void
    {
        $this->actingAs(User::factory()->create(['is_platform_admin' => true]))
            ->get(route('platform.dashboard', absolute: false))
            ->assertOk()
            ->assertSee('/platform/members', false)
            ->assertDontSee('Próximamente')
            ->assertDontSee('Módulos aún no disponibles');
    }

    public function test_empty_members_page_shows_empty_states(): void
    {
        $this->actingAs(User::factory()->create(['is_platform_admin' => true]))
            ->get('/platform/members')
            ->assertOk()
            ->assertSee('No hay miembros del equipo.')
            ->assertSee('No hay clientes registrados.');
    }

    public function test_members_and_customers_have_independent_pagination(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Barbershop::factory()->create();
        Membership::factory()->count(16)->create(['barbershop_id' => $shop->id]);
        Customer::factory()->count(16)->create(['barbershop_id' => $shop->id]);

        $this->actingAs($admin)->get('/platform/members?barbershop='.$shop->slug.'&team_page=2')
            ->assertOk()
            ->assertViewHas('memberships', fn ($memberships): bool => $memberships->count() === 1 && $memberships->currentPage() === 2)
            ->assertViewHas('customers', fn ($customers): bool => $customers->count() === 15 && $customers->currentPage() === 1)
            ->assertSee('barbershop='.$shop->slug, false)
            ->assertSee('customers_page=2', false);
    }
}
