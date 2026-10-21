<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarberAppointmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_barber_reschedules_own_appointment_with_service_duration(): void
    {
        [$barbershop, $user, $barber, $service, $appointment] = $this->appointmentContext();

        $this->actingAs($user)
            ->put(route('tenant.appointments.reschedule', [$barbershop, $appointment], false), [
                'starts_at' => '2026-10-05T11:00',
                'barber_id' => 999999,
                'customer_id' => 999999,
                'service_id' => 999999,
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false));

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'customer_id' => null,
            'starts_at' => '2026-10-05 11:00:00',
            'ends_at' => '2026-10-05 11:30:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
    }

    public function test_barber_can_cancel_complete_and_mark_own_appointment_no_show(): void
    {
        foreach ([
            ['cancel', Appointment::STATUS_CANCELLED],
            ['complete', Appointment::STATUS_COMPLETED],
            ['no-show', Appointment::STATUS_NO_SHOW],
        ] as [$action, $status]) {
            [$barbershop, $user, , , $appointment] = $this->appointmentContext();

            $this->actingAs($user)
                ->post(route("tenant.appointments.{$action}", [$barbershop, $appointment], false))
                ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false));

            $this->assertDatabaseHas('appointments', [
                'id' => $appointment->id,
                'status' => $status,
                'starts_at' => '2026-10-05 10:00:00',
                'ends_at' => '2026-10-05 10:30:00',
            ]);
        }
    }

    public function test_owner_admin_and_platform_admin_can_manage_tenant_appointments(): void
    {
        foreach ([Membership::ROLE_OWNER, Membership::ROLE_ADMIN, 'platform-admin'] as $role) {
            [$barbershop, , , , $appointment] = $this->appointmentContext();
            $user = User::factory()->create(['is_platform_admin' => $role === 'platform-admin']);

            if ($role !== 'platform-admin') {
                Membership::factory()->for($user)->for($barbershop)->create(['role' => $role]);
            }

            $this->actingAs($user)
                ->post(route('tenant.appointments.complete', [$barbershop, $appointment], false))
                ->assertRedirect();

            $this->assertDatabaseHas('appointments', [
                'id' => $appointment->id,
                'status' => Appointment::STATUS_COMPLETED,
            ]);
        }
    }

    public function test_barber_cannot_manage_another_barbers_appointment(): void
    {
        [$barbershop, $user, , $service] = $this->appointmentContext();
        $otherBarber = Barber::factory()->for($barbershop)->create();
        $appointment = Appointment::factory()->for($barbershop)->for($otherBarber)->for($service)->create([
            'starts_at' => '2026-10-05 10:00:00',
            'ends_at' => '2026-10-05 10:30:00',
        ]);

        $this->actingAs($user)
            ->post(route('tenant.appointments.cancel', [$barbershop, $appointment], false))
            ->assertForbidden();

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => Appointment::STATUS_SCHEDULED]);
    }

    public function test_unrelated_tenant_user_cannot_transition_appointment(): void
    {
        [$barbershop, , , , $appointment] = $this->appointmentContext();
        $unrelatedUser = User::factory()->create();
        $this->actingAs($unrelatedUser)
            ->post(route('tenant.appointments.complete', [$barbershop, $appointment], false))
            ->assertForbidden();

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => Appointment::STATUS_SCHEDULED]);
    }

    public function test_reschedule_rejects_overlap_and_preserves_all_appointment_fields(): void
    {
        [$barbershop, $user, $barber, $service, $appointment] = $this->appointmentContext();
        $blocking = Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'starts_at' => '2026-10-05 11:00:00',
            'ends_at' => '2026-10-05 11:30:00',
        ]);
        $original = [
            ...$appointment->only(['barbershop_id', 'barber_id', 'service_id', 'customer_id', 'status']),
            'starts_at' => $appointment->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $appointment->ends_at->format('Y-m-d H:i:s'),
        ];

        $this->actingAs($user)
            ->from(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->put(route('tenant.appointments.reschedule', [$barbershop, $appointment], false), ['starts_at' => '2026-10-05T11:00'])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertSessionHasErrors('starts_at');

        $freshAppointment = $appointment->fresh();
        $this->assertSame($original, [
            ...$freshAppointment->only(['barbershop_id', 'barber_id', 'service_id', 'customer_id', 'status']),
            'starts_at' => $freshAppointment->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $freshAppointment->ends_at->format('Y-m-d H:i:s'),
        ]);
        $this->assertDatabaseHas('appointments', ['id' => $blocking->id, 'status' => Appointment::STATUS_SCHEDULED]);
    }

    public function test_terminal_appointment_cannot_be_rescheduled_or_transitioned_again(): void
    {
        [$barbershop, $user, , , $appointment] = $this->appointmentContext();
        $appointment->update(['status' => Appointment::STATUS_CANCELLED]);
        $original = [
            'starts_at' => $appointment->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $appointment->ends_at->format('Y-m-d H:i:s'),
            'status' => $appointment->status,
        ];

        $this->actingAs($user)
            ->put(route('tenant.appointments.reschedule', [$barbershop, $appointment], false), ['starts_at' => '2026-10-05T11:00'])
            ->assertNotFound();

        $this->actingAs($user)
            ->post(route('tenant.appointments.complete', [$barbershop, $appointment], false))
            ->assertNotFound();

        $freshAppointment = $appointment->fresh();
        $this->assertSame($original, [
            'starts_at' => $freshAppointment->starts_at->format('Y-m-d H:i:s'),
            'ends_at' => $freshAppointment->ends_at->format('Y-m-d H:i:s'),
            'status' => $freshAppointment->status,
        ]);
    }

    /** @return array{0: Barbershop, 1: User, 2: Barber, 3: Service, 4: Appointment} */
    private function appointmentContext(): array
    {
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $user = User::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create(['role' => Membership::ROLE_BARBER]);
        $barber = Barber::factory()->for($barbershop)->create(['user_id' => $user->id, 'active' => true]);
        $service = Service::factory()->for($barbershop)->create(['active' => true, 'duration_minutes' => 30]);
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
        $appointment = Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'starts_at' => '2026-10-05 10:00:00',
            'ends_at' => '2026-10-05 10:30:00',
        ]);

        return [$barbershop, $user, $barber, $service, $appointment];
    }
}
