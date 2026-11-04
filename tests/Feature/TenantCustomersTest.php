<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantCustomersTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_admin_can_view_only_their_alphabetized_customers(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($admin)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);
        Customer::factory()->for($barbershop)->create(['name' => 'Zoe Cliente', 'phone' => '555-0002', 'email' => 'zoe@example.com']);
        Customer::factory()->for($barbershop)->create(['name' => 'Ana Cliente', 'phone' => '555-0001', 'email' => 'ana@example.com']);
        Customer::factory()->for($otherBarbershop)->create(['name' => 'Cliente ajeno', 'email' => 'ajeno@example.com']);

        foreach ([$owner, $admin] as $manager) {
            $response = $this->actingAs($manager)->get(route('tenant.customers.index', $barbershop, false));

            $response->assertOk()
                ->assertSee('Ana Cliente')
                ->assertSee('ana@example.com')
                ->assertSee('555-0001')
                ->assertSee('Zoe Cliente')
                ->assertDontSee('Cliente ajeno')
                ->assertViewHas('customers', fn ($customers): bool => $customers->pluck('name')->all() === ['Ana Cliente', 'Zoe Cliente']);
        }
    }

    public function test_manager_cannot_access_another_tenants_customer_directory(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Customer::factory()->for($otherBarbershop)->create(['name' => 'Cliente de otra barbería']);

        $this->actingAs($owner)
            ->get(route('tenant.customers.index', $otherBarbershop, false))
            ->assertForbidden();
    }

    public function test_platform_admin_can_view_customers_for_support(): void
    {
        $barbershop = Barbershop::factory()->create();
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);
        Customer::factory()->for($barbershop)->create(['name' => 'Cliente de soporte']);

        $this->actingAs($platformAdmin)
            ->get(route('tenant.customers.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Cliente de soporte');
    }

    public function test_customer_page_requires_authentication(): void
    {
        $barbershop = Barbershop::factory()->create();

        $this->get(route('tenant.customers.index', $barbershop, false))
            ->assertRedirect(route('login'));
    }

    public function test_barber_inactive_member_customer_account_and_outsider_cannot_view_customers(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barber = User::factory()->create();
        $inactiveAdmin = User::factory()->create();
        $customerAccount = User::factory()->create();
        $outsider = User::factory()->create();
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        Membership::factory()->for($inactiveAdmin)->for($barbershop)->create([
            'role' => Membership::ROLE_ADMIN,
            'status' => Membership::STATUS_INACTIVE,
        ]);
        Customer::factory()->for($barbershop)->for($customerAccount, 'user')->create();

        foreach ([$barber, $inactiveAdmin, $customerAccount, $outsider] as $user) {
            $this->actingAs($user)
                ->get(route('tenant.customers.index', $barbershop, false))
                ->assertForbidden();
        }
    }

    public function test_customer_page_shows_empty_state_and_paginates_customers(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get(route('tenant.customers.index', $barbershop, false))
            ->assertOk()
            ->assertSee('Todavía no hay clientes registrados.');

        Customer::factory()->count(16)->for($barbershop)->sequence(
            ['name' => 'Cliente 01'],
            ['name' => 'Cliente 02'],
            ['name' => 'Cliente 03'],
            ['name' => 'Cliente 04'],
            ['name' => 'Cliente 05'],
            ['name' => 'Cliente 06'],
            ['name' => 'Cliente 07'],
            ['name' => 'Cliente 08'],
            ['name' => 'Cliente 09'],
            ['name' => 'Cliente 10'],
            ['name' => 'Cliente 11'],
            ['name' => 'Cliente 12'],
            ['name' => 'Cliente 13'],
            ['name' => 'Cliente 14'],
            ['name' => 'Cliente 15'],
            ['name' => 'Cliente 16'],
        )->create();

        $this->get(route('tenant.customers.index', $barbershop, false))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers): bool => $customers->count() === 15 && $customers->currentPage() === 1)
            ->assertSee('customers_page=2', false);

        $this->get(route('tenant.customers.index', $barbershop, false).'?customers_page=2')
            ->assertOk()
            ->assertViewHas('customers', fn ($customers): bool => $customers->count() === 1 && $customers->currentPage() === 2)
            ->assertSee('Cliente 16');
    }

    public function test_customer_navigation_is_visible_only_to_managers_and_has_an_icon(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        $barber = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        $href = route('tenant.customers.index', $barbershop);

        $managerResponse = $this->actingAs($owner)->get(route('tenant.customers.index', $barbershop, false));
        $managerResponse->assertOk();
        $managerDocument = new DOMDocument;
        $managerDocument->loadHTML($managerResponse->getContent());
        $managerXPath = new DOMXPath($managerDocument);
        $customerLinks = $managerXPath->query('//a[@href="'.$href.'"]');

        $this->assertSame(1, $customerLinks->length);
        $this->assertSame(1, $managerXPath->query('.//svg', $customerLinks->item(0))->length);

        $this->actingAs($barber)
            ->get(route('tenant.dashboard', $barbershop, false))
            ->assertOk()
            ->assertDontSee($href, false);
    }
}
