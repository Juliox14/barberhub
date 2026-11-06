<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAppointmentAttendanceTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_barber_and_owner_cannot_complete_or_mark_future_appointments_as_no_show(): void
    {
        foreach ([Membership::ROLE_BARBER, Membership::ROLE_OWNER] as $role) {
            foreach (['2026-10-05 10:30:00', '2026-10-06 10:00:00'] as $startsAt) {
                [$barbershop, $user, $appointment] = $this->appointmentContext($role, $startsAt);
                $this->travelTo(Carbon::parse('2026-10-05 10:00:00', $barbershop->timezone));

                foreach (['complete', 'no-show'] as $action) {
                    $this->actingAs($user)
                        ->from(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
                        ->post(route("tenant.appointments.{$action}", [$barbershop, $appointment], false))
                        ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
                        ->assertSessionHasErrors(['starts_at' => 'La asistencia solo se puede confirmar después de la hora de inicio de la cita.']);

                    $this->assertDatabaseHas('appointments', [
                        'id' => $appointment->id,
                        'status' => Appointment::STATUS_SCHEDULED,
                        'starts_at' => $startsAt,
                    ]);
                    $this->assertDatabaseCount('appointment_events', 0);
                }
            }
        }
    }

    public function test_barber_and_owner_cannot_confirm_attendance_at_the_exact_start_time(): void
    {
        foreach ([Membership::ROLE_BARBER, Membership::ROLE_OWNER] as $role) {
            [$barbershop, $user, $appointment] = $this->appointmentContext($role, '2026-10-05 10:00:00');
            $this->travelTo(Carbon::parse('2026-10-05 10:00:00', $barbershop->timezone));

            foreach (['complete', 'no-show'] as $action) {
                $this->actingAs($user)
                    ->post(route("tenant.appointments.{$action}", [$barbershop, $appointment], false))
                    ->assertSessionHasErrors('starts_at');

                $this->assertDatabaseHas('appointments', [
                    'id' => $appointment->id,
                    'status' => Appointment::STATUS_SCHEDULED,
                ]);
            }
        }
    }

    public function test_past_appointment_can_be_completed_using_the_barbershop_wall_clock(): void
    {
        [$barbershop, $owner, $appointment] = $this->appointmentContext(Membership::ROLE_OWNER, '2026-10-05 09:59:59');
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00', $barbershop->timezone));

        $this->actingAs($owner)
            ->post(route('tenant.appointments.complete', [$barbershop, $appointment], false))
            ->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_COMPLETED,
        ]);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id,
            'event_kind' => Appointment::STATUS_COMPLETED,
        ]);
    }

    public function test_future_appointment_can_still_be_cancelled(): void
    {
        [$barbershop, $owner, $appointment] = $this->appointmentContext(Membership::ROLE_OWNER, '2026-10-05 10:30:00');
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00', $barbershop->timezone));

        $this->actingAs($owner)
            ->post(route('tenant.appointments.cancel', [$barbershop, $appointment], false))
            ->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_CANCELLED,
        ]);
    }

    public function test_attendance_forms_are_hidden_until_appointment_start_has_passed(): void
    {
        [$barbershop, $owner, $appointment] = $this->appointmentContext(Membership::ROLE_OWNER, '2026-10-05 10:30:00');
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00', $barbershop->timezone));

        $this->actingAs($owner)
            ->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertDontSee('Completar')
            ->assertDontSee('No asistió')
            ->assertSee('Cancelar cita')
            ->assertSee('Reprogramar');

        $appointment->update(['starts_at' => '2026-10-05 10:00:00']);

        $this->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertDontSee('Completar')
            ->assertDontSee('No asistió');

        $appointment->update(['starts_at' => '2026-10-05 09:59:59']);

        $this->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertSee('Completar')
            ->assertSee('No asistió');
    }

    /** @return array{0: Barbershop, 1: User, 2: Appointment} */
    private function appointmentContext(string $role, string $startsAt): array
    {
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $user = User::factory()->create();
        Membership::factory()->for($user)->for($barbershop)->create(['role' => $role]);
        $barber = Barber::factory()->for($barbershop)->create([
            'user_id' => $role === Membership::ROLE_BARBER ? $user->id : User::factory()->create()->id,
            'active' => true,
        ]);
        $service = Service::factory()->for($barbershop)->create(['active' => true, 'duration_minutes' => 30]);
        $appointment = Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'starts_at' => $startsAt,
            'ends_at' => Carbon::parse($startsAt)->addMinutes(30)->format('Y-m-d H:i:s'),
        ]);

        return [$barbershop, $user, $appointment];
    }
}
