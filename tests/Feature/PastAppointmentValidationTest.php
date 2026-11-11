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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PastAppointmentValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_staff_cannot_create_an_appointment_earlier_today(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 10:30:00', 'America/Mexico_City'));
        [$barbershop, $owner, $barber, $service] = $this->scheduleableRecords();

        $this->actingAs($owner)
            ->from(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'customer_id' => '',
                'starts_at' => '2026-10-05T10:15',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertSessionHasErrors([
                'starts_at' => 'La cita debe programarse en una fecha y hora futuras.',
            ]);

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_events', 0);
    }

    public function test_staff_cannot_create_an_appointment_on_a_past_day(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 10:30:00', 'America/Mexico_City'));
        [$barbershop, $owner, $barber, $service] = $this->scheduleableRecords();

        $this->actingAs($owner)
            ->from(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-09-28'], false))
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'customer_id' => '',
                'starts_at' => '2026-09-28T10:15',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-09-28'], false))
            ->assertSessionHasErrors([
                'starts_at' => 'La cita debe programarse en una fecha y hora futuras.',
            ]);

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_events', 0);
    }

    public function test_staff_cannot_create_an_appointment_at_the_current_time(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 10:30:00', 'America/Mexico_City'));
        [$barbershop, $owner, $barber, $service] = $this->scheduleableRecords();

        $this->actingAs($owner)
            ->from(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'customer_id' => '',
                'starts_at' => '2026-10-05T10:30',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertSessionHasErrors([
                'starts_at' => 'La cita debe programarse en una fecha y hora futuras.',
            ]);

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_staff_can_create_an_appointment_later_today(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 10:30:00', 'America/Mexico_City'));
        [$barbershop, $owner, $barber, $service] = $this->scheduleableRecords();

        $this->actingAs($owner)
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'customer_id' => '',
                'starts_at' => '2026-10-05T10:45',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false));

        $this->assertDatabaseHas('appointments', [
            'barbershop_id' => $barbershop->id,
            'starts_at' => '2026-10-05 10:45:00',
            'ends_at' => '2026-10-05 11:00:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => Appointment::query()->sole()->id,
            'event_kind' => 'created',
        ]);
    }

    public function test_staff_cannot_create_an_appointment_before_local_midnight_even_when_utc_date_differs(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 00:30:00', 'America/Mexico_City'));
        [$barbershop, $owner, $barber, $service] = $this->scheduleableRecords();
        BusinessHour::factory()->for($barbershop)->create([
            'weekday' => BusinessHour::WEEKDAY_SUNDAY,
            'opens_at' => '00:00',
            'closes_at' => '23:59',
            'closed' => false,
        ]);
        BarberAvailability::factory()->for($barber)->create([
            'weekday' => BusinessHour::WEEKDAY_SUNDAY,
            'starts_at' => '00:00',
            'ends_at' => '23:59',
            'active' => true,
        ]);

        $this->actingAs($owner)
            ->from(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-04'], false))
            ->post(route('tenant.appointments.store', $barbershop, false), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'customer_id' => '',
                'starts_at' => '2026-10-04T23:30',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-04'], false))
            ->assertSessionHasErrors([
                'starts_at' => 'La cita debe programarse en una fecha y hora futuras.',
            ]);

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_staff_cannot_reschedule_an_appointment_to_earlier_today(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 10:30:00', 'America/Mexico_City'));
        [$barbershop, $owner, $barber, $service] = $this->scheduleableRecords();
        $appointment = Appointment::factory()->for($barbershop)->for($barber)->for($service)->create([
            'starts_at' => '2026-10-05 11:00:00',
            'ends_at' => '2026-10-05 11:30:00',
        ]);
        $original = $appointment->getRawOriginal('starts_at');

        $this->actingAs($owner)
            ->from(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->put(route('tenant.appointments.reschedule', [$barbershop, $appointment], false), [
                'starts_at' => '2026-10-05T10:15',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertSessionHasErrors([
                'starts_at' => 'La cita debe programarse en una fecha y hora futuras.',
            ]);

        $this->assertSame($original, $appointment->fresh()->getRawOriginal('starts_at'));
        $this->assertDatabaseCount('appointment_events', 0);
    }

    /**
     * @return array{0: Barbershop, 1: User, 2: Barber, 3: Service}
     */
    private function scheduleableRecords(): array
    {
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create([
            'user_id' => User::factory()->create()->id,
            'active' => true,
        ]);
        $service = Service::factory()->for($barbershop)->create(['active' => true, 'duration_minutes' => 15]);
        $barber->services()->syncWithoutDetaching([$service->id]);
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

        return [$barbershop, $owner, $barber, $service];
    }
}
