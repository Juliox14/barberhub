<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantGridSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_price_sort_is_numeric_and_header_links_preserve_route_parameters(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        Service::factory()->for($barbershop)->create(['name' => 'Servicio caro', 'price' => 100, 'duration_minutes' => 120]);
        Service::factory()->for($barbershop)->create(['name' => 'Servicio barato', 'price' => 20, 'duration_minutes' => 30]);

        $this->actingAs($owner)
            ->get(route('tenant.services.index', $barbershop, false).'?sort=price&direction=asc&filter=active&barbershop=invalid-tenant')
            ->assertOk()
            ->assertViewHas('services', fn ($services): bool => $services->pluck('name')->all() === ['Servicio barato', 'Servicio caro'])
            ->assertSee(route('tenant.services.index', ['barbershop' => $barbershop, 'sort' => 'price', 'direction' => 'desc', 'filter' => 'active'], false));

        $this->get(route('tenant.services.index', ['barbershop' => $barbershop, 'sort' => 'price', 'direction' => 'desc'], false))
            ->assertViewHas('services', fn ($services): bool => $services->pluck('name')->all() === ['Servicio caro', 'Servicio barato']);
        $this->get(route('tenant.services.index', ['barbershop' => $barbershop, 'sort' => 'duration', 'direction' => 'asc'], false))
            ->assertViewHas('services', fn ($services): bool => $services->pluck('duration_minutes')->all() === [30, 120]);
        $this->get(route('tenant.services.index', ['barbershop' => $barbershop, 'sort' => 'duration', 'direction' => 'desc'], false))
            ->assertViewHas('services', fn ($services): bool => $services->pluck('duration_minutes')->all() === [120, 30]);
        $this->get(route('tenant.services.index', ['barbershop' => $barbershop, 'sort' => 'unknown', 'direction' => 'asc'], false))
            ->assertViewHas('services', fn ($services): bool => $services->pluck('name')->all() === ['Servicio barato', 'Servicio caro']);
        $this->get(route('tenant.services.index', ['barbershop' => $barbershop, 'sort' => 'price', 'direction' => 'sideways'], false))
            ->assertViewHas('services', fn ($services): bool => $services->pluck('name')->all() === ['Servicio caro', 'Servicio barato']);
    }

    public function test_appointments_sort_by_chronological_start_time(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create();
        $service = Service::factory()->for($barbershop)->create();
        $customer = Customer::factory()->for($barbershop)->create();
        $later = Appointment::factory()->for($barbershop)->for($barber)->for($service)->for($customer)->create(['starts_at' => '2026-10-05 15:00:00']);
        $earlier = Appointment::factory()->for($barbershop)->for($barber)->for($service)->for($customer)->create(['starts_at' => '2026-10-05 09:00:00']);

        $this->actingAs($owner)
            ->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05', 'sort' => 'time', 'direction' => 'asc'], false))
            ->assertOk()
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->modelKeys() === [$earlier->id, $later->id])
            ->assertSee('date=2026-10-05', false);

        $this->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05', 'sort' => 'time', 'direction' => 'desc'], false))
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->modelKeys() === [$later->id, $earlier->id]);
        foreach ([
            ['sort' => 'unknown', 'direction' => 'desc'],
            ['sort' => 'time', 'direction' => 'sideways'],
        ] as $invalidSort) {
            $this->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05', ...$invalidSort], false))
                ->assertViewHas('appointments', fn ($appointments): bool => $appointments->modelKeys() === [$earlier->id, $later->id]);
        }
    }

    public function test_members_sort_by_displayed_role_in_both_directions_and_keep_name_default_for_invalid_input(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create(['name' => 'Z Owner']);
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $admin = User::factory()->create(['name' => 'A Admin']);
        Membership::factory()->for($admin)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);
        $barber = User::factory()->create(['name' => 'M Barber']);
        Membership::factory()->for($barber)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);

        $this->actingAs($owner)
            ->get(route('tenant.members.index', ['barbershop' => $barbershop, 'sort' => 'role', 'direction' => 'asc'], false))
            ->assertViewHas('memberships', fn ($memberships): bool => $memberships->map(fn ($membership) => $membership->user->name)->all() === ['A Admin', 'M Barber', 'Z Owner']);
        $this->get(route('tenant.members.index', ['barbershop' => $barbershop, 'sort' => 'role', 'direction' => 'desc'], false))
            ->assertViewHas('memberships', fn ($memberships): bool => $memberships->map(fn ($membership) => $membership->user->name)->all() === ['Z Owner', 'M Barber', 'A Admin']);
        $this->get(route('tenant.members.index', ['barbershop' => $barbershop, 'sort' => 'unknown', 'direction' => 'desc'], false))
            ->assertViewHas('memberships', fn ($memberships): bool => $memberships->map(fn ($membership) => $membership->user->name)->all() === ['A Admin', 'M Barber', 'Z Owner']);
        $this->get(route('tenant.members.index', ['barbershop' => $barbershop, 'sort' => 'role', 'direction' => 'sideways'], false))
            ->assertViewHas('memberships', fn ($memberships): bool => $memberships->map(fn ($membership) => $membership->user->name)->all() === ['A Admin', 'M Barber', 'Z Owner']);
    }

    public function test_barbers_sort_by_displayed_status_in_both_directions_and_keep_active_default_for_invalid_input(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $activeUser = User::factory()->create(['name' => 'A Active']);
        $inactiveUser = User::factory()->create(['name' => 'Z Inactive']);
        $active = Barber::factory()->for($barbershop)->for($activeUser, 'user')->create(['display_name' => 'A Agenda', 'active' => true]);
        $inactive = Barber::factory()->for($barbershop)->for($inactiveUser, 'user')->create(['display_name' => 'Z Agenda', 'active' => false]);

        $this->actingAs($owner)
            ->get(route('tenant.barbers.index', ['barbershop' => $barbershop, 'sort' => 'status', 'direction' => 'asc'], false))
            ->assertViewHas('barbers', fn ($barbers): bool => $barbers->modelKeys() === [$active->id, $inactive->id]);
        $this->get(route('tenant.barbers.index', ['barbershop' => $barbershop, 'sort' => 'status', 'direction' => 'desc'], false))
            ->assertViewHas('barbers', fn ($barbers): bool => $barbers->modelKeys() === [$inactive->id, $active->id]);
        $this->get(route('tenant.barbers.index', ['barbershop' => $barbershop, 'sort' => 'unknown', 'direction' => 'asc'], false))
            ->assertViewHas('barbers', fn ($barbers): bool => $barbers->modelKeys() === [$active->id, $inactive->id]);
        $this->get(route('tenant.barbers.index', ['barbershop' => $barbershop, 'sort' => 'status', 'direction' => 'sideways'], false))
            ->assertViewHas('barbers', fn ($barbers): bool => $barbers->modelKeys() === [$inactive->id, $active->id]);
    }
}
