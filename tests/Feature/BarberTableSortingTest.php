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

class BarberTableSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_barber_sorts_own_appointments_by_customer_and_time_without_losing_date_filter(): void
    {
        $barbershop = Barbershop::factory()->create();
        [$barberUser, $barber, $service] = $this->barberRecords($barbershop);
        $zoe = Customer::factory()->for($barbershop)->create(['name' => 'Zoe']);
        $alice = Customer::factory()->for($barbershop)->create(['name' => 'Alice']);
        $bella = Customer::factory()->for($barbershop)->create(['name' => 'Bella']);

        Appointment::factory()->for($barbershop)->for($barber)->for($service)->for($zoe)->create(['starts_at' => '2026-10-05 11:00:00']);
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->for($alice)->create(['starts_at' => '2026-10-05 09:00:00']);
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->for($bella)->create(['starts_at' => '2026-10-05 10:00:00']);

        $otherBarber = Barber::factory()->for($barbershop)->create();
        $otherBarberCustomer = Customer::factory()->for($barbershop)->create(['name' => 'Other barber customer']);
        Appointment::factory()->for($barbershop)->for($otherBarber)->for($service)->for($otherBarberCustomer)->create(['starts_at' => '2026-10-05 08:00:00']);

        $otherBarbershop = Barbershop::factory()->create();
        $otherTenantCustomer = Customer::factory()->for($otherBarbershop)->create(['name' => 'Other tenant customer']);
        Appointment::factory()->for($otherBarbershop)->for($service)->for($otherTenantCustomer)->create(['starts_at' => '2026-10-05 07:00:00']);
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->for($zoe)->create(['starts_at' => '2026-10-06 09:00:00']);

        $this->actingAs($barberUser)
            ->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05', 'sort' => 'customer', 'direction' => 'asc'], false))
            ->assertOk()
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->pluck('customer.name')->all() === ['Alice', 'Bella', 'Zoe'])
            ->assertSee(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05', 'sort' => 'customer', 'direction' => 'desc'], false))
            ->assertDontSee('Other tenant customer');

        $this->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05', 'sort' => 'customer', 'direction' => 'desc'], false))
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->pluck('customer.name')->all() === ['Zoe', 'Bella', 'Alice']);

        $this->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05', 'sort' => 'time', 'direction' => 'asc'], false))
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->pluck('starts_at')->map(fn ($startsAt): string => $startsAt->format('H:i'))->all() === ['09:00', '10:00', '11:00']);

        $this->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05', 'sort' => 'time', 'direction' => 'desc'], false))
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->pluck('starts_at')->map(fn ($startsAt): string => $startsAt->format('H:i'))->all() === ['11:00', '10:00', '09:00']);
    }

    public function test_barber_cannot_view_management_only_customer_or_barber_tables(): void
    {
        $barbershop = Barbershop::factory()->create();
        [$barberUser] = $this->barberRecords($barbershop);

        $this->actingAs($barberUser)
            ->get(route('tenant.customers.index', $barbershop, false))
            ->assertForbidden();

        $this->get(route('tenant.barbers.index', $barbershop, false))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Barber, 2: Service}
     */
    private function barberRecords(Barbershop $barbershop): array
    {
        $barberUser = User::factory()->create();
        Membership::factory()->for($barberUser)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        $barber = Barber::factory()->for($barbershop)->create(['user_id' => $barberUser->id, 'active' => true]);
        $service = Service::factory()->for($barbershop)->create();

        return [$barberUser, $barber, $service];
    }
}
