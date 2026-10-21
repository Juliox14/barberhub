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

class BarberAppointmentCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_barber_sees_own_appointment_form_and_tenant_customers(): void
    {
        $barbershop = Barbershop::factory()->create();
        [$barberUser, $barber, $service, $customer] = $this->barberRecords($barbershop);
        $otherBarber = Barber::factory()->for($barbershop)->create();
        $otherBarberCustomer = Customer::factory()->for($barbershop)->create(['name' => 'Cliente ajeno']);

        $this->actingAs($barberUser)
            ->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertOk()
            ->assertSee('Crear cita')
            ->assertSee($service->name)
            ->assertSee($customer->name)
            ->assertSee($otherBarberCustomer->name)
            ->assertDontSee('name="barber_id"', false)
            ->assertViewHas('canManageMembers', false);
    }

    public function test_barber_creates_name_only_customer_and_appointment_without_user(): void
    {
        $barbershop = Barbershop::factory()->create();
        [$barberUser, $barber, $service] = $this->barberRecords($barbershop);
        $usersBefore = User::count();

        $this->actingAs($barberUser)
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'service_id' => $service->id,
                'new_customer_name' => '  Walk-in sin cuenta  ',
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false));

        $customer = Customer::query()->where('name', 'Walk-in sin cuenta')->sole();
        $this->assertSame($barbershop->id, $customer->barbershop_id);
        $this->assertNull($customer->user_id);
        $this->assertSame($usersBefore, User::count());
        $this->assertDatabaseHas('appointments', [
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'customer_id' => $customer->id,
            'starts_at' => '2026-10-05 11:00:00',
            'ends_at' => '2026-10-05 11:30:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
    }

    public function test_barber_form_payload_accepts_existing_customer_with_empty_new_name(): void
    {
        $barbershop = Barbershop::factory()->create();
        [$barberUser, $barber, $service, $customer] = $this->barberRecords($barbershop);

        $this->actingAs($barberUser)
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'service_id' => $service->id,
                'customer_id' => (string) $customer->id,
                'new_customer_name' => '',
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false));

        $this->assertDatabaseHas('appointments', [
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'customer_id' => $customer->id,
        ]);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_barber_form_payload_accepts_new_name_with_empty_customer_selection(): void
    {
        $barbershop = Barbershop::factory()->create();
        [$barberUser, $barber, $service] = $this->barberRecords($barbershop);

        $this->actingAs($barberUser)
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'service_id' => $service->id,
                'customer_id' => '',
                'new_customer_name' => 'Cliente de formulario',
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false));

        $customer = Customer::query()->where('name', 'Cliente de formulario')->sole();
        $this->assertNull($customer->user_id);
        $this->assertDatabaseHas('appointments', [
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_platform_admin_with_barber_membership_can_select_another_active_barber(): void
    {
        $barbershop = Barbershop::factory()->create();
        [$admin, $ownBarber, $service] = $this->barberRecords($barbershop);
        $admin->update(['is_platform_admin' => true]);
        $otherBarber = Barber::factory()->for($barbershop)->create(['active' => true]);
        BarberAvailability::factory()->for($otherBarber)->create([
            'weekday' => BusinessHour::WEEKDAY_MONDAY,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $otherBarber->id,
                'service_id' => $service->id,
                'customer_id' => '',
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false));

        $this->assertDatabaseHas('appointments', [
            'barber_id' => $otherBarber->id,
            'service_id' => $service->id,
            'customer_id' => null,
        ]);
        $this->assertDatabaseMissing('appointments', ['barber_id' => $ownBarber->id]);
    }

    public function test_barber_creates_appointment_for_existing_tenant_customer(): void
    {
        $barbershop = Barbershop::factory()->create();
        [$barberUser, $barber, $service, $customer] = $this->barberRecords($barbershop);

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

    public function test_barber_cannot_select_another_barber_or_another_tenant_customer(): void
    {
        $barbershop = Barbershop::factory()->create();
        $otherBarbershop = Barbershop::factory()->create();
        [$barberUser, , $service] = $this->barberRecords($barbershop);
        $otherBarber = Barber::factory()->for($barbershop)->create();
        $otherCustomer = Customer::factory()->for($otherBarbershop)->create();

        $this->actingAs($barberUser)
            ->from(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $otherBarber->id,
                'service_id' => $service->id,
                'customer_id' => $otherCustomer->id,
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertSessionHasErrors(['barber_id', 'customer_id']);

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseMissing('customers', ['id' => $otherCustomer->id, 'barbershop_id' => $barbershop->id]);
    }

    public function test_barber_must_choose_one_customer_and_cannot_submit_a_blank_name(): void
    {
        $barbershop = Barbershop::factory()->create();
        [$barberUser, , $service, $customer] = $this->barberRecords($barbershop);
        $url = route('tenant.appointments.store', $barbershop, false);
        $referer = route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false);

        $this->actingAs($barberUser)
            ->from($referer)
            ->post($url, [
                'service_id' => $service->id,
                'customer_id' => $customer->id,
                'new_customer_name' => 'No se permiten ambos',
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect($referer)
            ->assertSessionHasErrors(['customer_id', 'new_customer_name']);

        $this->actingAs($barberUser)
            ->from($referer)
            ->post($url, [
                'service_id' => $service->id,
                'new_customer_name' => '   ',
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect($referer)
            ->assertSessionHasErrors('new_customer_name');

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_rejected_schedule_does_not_leave_a_new_customer(): void
    {
        $barbershop = Barbershop::factory()->create();
        [$barberUser, , $service] = $this->barberRecords($barbershop);
        $customerCount = Customer::count();
        Appointment::factory()->for($barbershop)->for(Barber::query()->where('user_id', $barberUser->id)->firstOrFail())->for($service)->create([
            'starts_at' => '2026-10-05 11:00:00',
            'ends_at' => '2026-10-05 11:30:00',
        ]);

        $this->actingAs($barberUser)
            ->from(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'service_id' => $service->id,
                'customer_id' => '',
                'new_customer_name' => 'No debe persistir',
                'starts_at' => '2026-10-05T11:00',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame($customerCount, Customer::count());
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseMissing('customers', ['name' => 'No debe persistir']);
    }

    /**
     * @return array{0: User, 1: Barber, 2: Service, 3: Customer}
     */
    private function barberRecords(Barbershop $barbershop): array
    {
        $barberUser = User::factory()->create();
        Membership::factory()->for($barberUser)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        $barber = Barber::factory()->for($barbershop)->create(['user_id' => $barberUser->id, 'active' => true]);
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

        return [$barberUser, $barber, $service, $customer];
    }
}
