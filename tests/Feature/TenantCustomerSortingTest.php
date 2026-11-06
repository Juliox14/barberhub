<?php

namespace Tests\Feature;

use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantCustomerSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sorting_applies_before_pagination_and_preserves_filters(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Customer::factory()->for($barbershop)->create(['name' => 'Zoe']);
        Customer::factory()->count(15)->for($barbershop)->sequence(
            ['name' => 'Alicia 01'], ['name' => 'Alicia 02'], ['name' => 'Alicia 03'],
            ['name' => 'Alicia 04'], ['name' => 'Alicia 05'], ['name' => 'Alicia 06'],
            ['name' => 'Alicia 07'], ['name' => 'Alicia 08'], ['name' => 'Alicia 09'],
            ['name' => 'Alicia 10'], ['name' => 'Alicia 11'], ['name' => 'Alicia 12'],
            ['name' => 'Alicia 13'], ['name' => 'Alicia 14'], ['name' => 'Alicia 15'],
        )->create();

        $this->actingAs($owner)
            ->get(route('tenant.customers.index', ['barbershop' => $barbershop, 'sort' => 'name', 'direction' => 'desc', 'customers_page' => 2, 'filter' => 'active'], false))
            ->assertOk()
            ->assertViewHas('customers', fn ($customers): bool => $customers->pluck('name')->all() === ['Alicia 01'])
            ->assertSee('sort=name&amp;direction=desc&amp;filter=active', false)
            ->assertSee(route('tenant.customers.index', ['barbershop' => $barbershop, 'sort' => 'name', 'direction' => 'asc', 'filter' => 'active'], false));

        $this->get(route('tenant.customers.index', ['barbershop' => $barbershop, 'sort' => 'name', 'direction' => 'desc', 'customers_page' => 1], false))
            ->assertViewHas('customers', fn ($customers): bool => $customers->pluck('name')->take(2)->all() === ['Zoe', 'Alicia 15']);

        $this->get(route('tenant.customers.index', ['barbershop' => $barbershop, 'sort' => 'name', 'direction' => 'asc', 'customers_page' => 1], false))
            ->assertViewHas('customers', fn ($customers): bool => $customers->pluck('name')->take(2)->all() === ['Alicia 01', 'Alicia 02']);

        foreach ([
            ['sort' => 'unknown', 'direction' => 'desc'],
            ['sort' => 'name', 'direction' => 'sideways'],
        ] as $invalidSort) {
            $this->get(route('tenant.customers.index', ['barbershop' => $barbershop, ...$invalidSort], false))
                ->assertViewHas('customers', fn ($customers): bool => $customers->pluck('name')->take(2)->all() === ['Alicia 01', 'Alicia 02']);
        }
    }
}
