<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAppointmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_tenant_appointments_and_create_one(): void
    {
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        [$barber, $service, $customer] = $this->scheduleableRecords($barbershop);

        $customer->update(['name' => 'Cliente de hoy']);
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'customer_id' => $customer->id,
            'starts_at' => '2026-10-05 10:00:00',
            'ends_at' => '2026-10-05 10:30:00',
        ]);
        $otherDateCustomer = Customer::factory()->for($barbershop)->create(['name' => 'Cliente de otro día']);
        Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'customer_id' => $otherDateCustomer->id,
            'starts_at' => '2026-10-06 10:00:00',
            'ends_at' => '2026-10-06 10:30:00',
        ]);

        $this->actingAs($owner)
            ->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertOk()
            ->assertSee('Agenda')
            ->assertSee('Cliente de hoy')
            ->assertSee('datetime="2026-10-05T10:00:00"', false)
            ->assertSee('>10:00</time>', false)
            ->assertViewHas('appointments', fn ($appointments): bool => $appointments->count() === 1
                && $appointments->first()->customer_id === $customer->id)
            ->assertSee($service->name)
            ->assertSee(route('tenant.appointments.index', $barbershop), false)
            ->assertSee('aria-current="page"', false);

        $this->actingAs($owner)
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'customer_id' => $customer->id,
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false));

        $this->assertDatabaseHas('appointments', [
            'barbershop_id' => $barbershop->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'customer_id' => $customer->id,
            'starts_at' => '2026-10-05 11:00:00',
            'ends_at' => '2026-10-05 11:30:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
    }

    public function test_barber_can_only_view_their_own_tenant_appointments(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barberUser = User::factory()->create();
        Membership::factory()->for($barberUser)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        [$barber, $service, $customer] = $this->scheduleableRecords($barbershop, $barberUser);
        $otherBarber = Barber::factory()->for($barbershop)->create();

        Appointment::factory()->for($barbershop)->for($barber)->for($service)->forCustomer()->create([
            'starts_at' => '2026-10-05 10:00:00',
            'ends_at' => '2026-10-05 10:30:00',
        ]);
        Appointment::factory()->for($barbershop)->for($otherBarber)->for($service)->forCustomer()->create([
            'starts_at' => '2026-10-05 11:00:00',
            'ends_at' => '2026-10-05 11:30:00',
        ]);

        $this->actingAs($barberUser)
            ->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertOk()
            ->assertSee($barber->display_name)
            ->assertDontSee($otherBarber->display_name)
            ->assertSee('Crear cita')
            ->assertDontSee('name="barber_id"', false);
    }

    public function test_admin_can_create_an_appointment(): void
    {
        $barbershop = Barbershop::factory()->create();
        $admin = User::factory()->create();
        Membership::factory()->for($admin)->for($barbershop)->create(['role' => Membership::ROLE_ADMIN]);
        [$barber, $service] = $this->scheduleableRecords($barbershop);

        $this->actingAs($admin)
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'customer_id' => '',
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false));

        $this->assertDatabaseHas('appointments', [
            'barbershop_id' => $barbershop->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
    }

    public function test_barber_can_create_an_appointment_for_an_existing_customer(): void
    {
        $barbershop = Barbershop::factory()->create();
        $barberUser = User::factory()->create();
        Membership::factory()->for($barberUser)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        [$barber, $service, $customer] = $this->scheduleableRecords($barbershop, $barberUser);

        $this->actingAs($barberUser)
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'service_id' => $service->id,
                'customer_id' => $customer->id,
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false));

        $this->assertDatabaseHas('appointments', [
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_owner_cannot_schedule_using_records_from_another_tenant(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        [$barber, $service] = $this->scheduleableRecords($otherBarbershop);

        $this->actingAs($owner)
            ->from(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertSessionHasErrors(['barber_id', 'service_id']);

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_owner_cannot_select_customer_from_another_tenant(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        [$barber, $service] = $this->scheduleableRecords($barbershop);
        $otherCustomer = Customer::factory()->for($otherBarbershop)->create();

        $this->actingAs($owner)
            ->from(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'customer_id' => $otherCustomer->id,
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertSessionHasErrors('customer_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_user_without_tenant_membership_cannot_view_appointments(): void
    {
        $user = User::factory()->create();
        $barbershop = Barbershop::factory()->create();

        $this->actingAs($user)
            ->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertForbidden();
    }

    public function test_user_without_tenant_membership_cannot_create_appointments(): void
    {
        $barbershop = Barbershop::factory()->create();
        $user = User::factory()->create();
        [$barber, $service] = $this->scheduleableRecords($barbershop);

        $this->actingAs($user)
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('appointments', 0);
    }

    /**
     * @return array{0: Barber, 1: Service, 2: Customer}
     */
    private function scheduleableRecords(Barbershop $barbershop, ?User $barberUser = null): array
    {
        $barber = Barber::factory()->for($barbershop)->create([
            'user_id' => $barberUser?->id ?? User::factory()->create()->id,
            'active' => true,
        ]);
        $service = Service::factory()->for($barbershop)->create(['active' => true, 'duration_minutes' => 30]);
        $customer = Customer::factory()->for($barbershop)->create();
        BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'closed' => false,
        ]);
        BarberAvailability::factory()->for($barber)->create([
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
            'active' => true,
        ]);

        return [$barber, $service, $customer];
    }
}
