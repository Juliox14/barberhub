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
use App\Support\AppointmentHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentHistoryPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_finished_appointment_history_and_recovery_preview_use_readable_labels_and_metadata(): void
    {
        $barbershop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create(['active' => true]);
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
            'starts_at' => '2026-10-05 11:00:00',
            'ends_at' => '2026-10-05 11:30:00',
            'status' => Appointment::STATUS_COMPLETED,
        ]);
        $event = app(AppointmentHistory::class)->record(
            $appointment,
            'completed',
            'staff',
            $owner,
            '2026-10-05 10:00:00',
            '2026-10-05 11:00:00',
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_COMPLETED,
        );

        $this->actingAs($owner)
            ->get(route('tenant.appointments.index', ['barbershop' => $barbershop, 'date' => '2026-10-05'], false))
            ->assertOk()
            ->assertSee('Completada')
            ->assertSee('Cita completada')
            ->assertSee('Valor anterior')
            ->assertSee('Valor actual')
            ->assertSee('05/10/2026')
            ->assertSee('event='.$event->id, false)
            ->assertDontSee('>completed<', false);

        $this->actingAs($owner)
            ->get(route('tenant.appointments.restore.show', [$barbershop, $appointment, 'event' => $event->id], false))
            ->assertOk()
            ->assertSee('Valor histórico que se restaurará')
            ->assertSee('Valor actual (debe coincidir al confirmar)')
            ->assertSee('Programada')
            ->assertSee('Completada')
            ->assertSee('05/10/2026')
            ->assertSee('10:00')
            ->assertSee('11:00')
            ->assertSee('name="event_id"', false)
            ->assertSee('name="expected_starts_at"', false)
            ->assertSee('name="expected_status"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="reason"', false)
            ->assertDontSee('>scheduled<', false)
            ->assertDontSee('>completed<', false);
    }
}
