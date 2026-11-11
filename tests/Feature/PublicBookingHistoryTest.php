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

class PublicBookingHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_create_reschedule_cancel_complete_and_no_show_record_actor_history(): void
    {
        $this->travelTo('2026-10-04 12:00:00 America/Mexico_City');

        $shop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($shop)->create(['role' => Membership::ROLE_OWNER]);
        $barberUser = User::factory()->create();
        Membership::factory()->for($barberUser)->for($shop)->create(['role' => Membership::ROLE_BARBER]);
        $barber = Barber::factory()->for($shop)->create(['user_id' => $barberUser->id]);
        $service = Service::factory()->for($shop)->create(['duration_minutes' => 30]);
        $barber->services()->syncWithoutDetaching([$service->id]);
        BusinessHour::factory()->for($shop)->create(['weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '18:00']);
        BarberAvailability::factory()->for($barber)->create(['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '18:00']);

        $this->actingAs($owner)->post(route('tenant.appointments.store', $shop, false), [
            'barber_id' => $barber->id, 'service_id' => $service->id, 'customer_id' => '', 'starts_at' => '2026-10-05T10:00',
        ])->assertRedirect();
        $appointment = Appointment::query()->sole();
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id, 'barbershop_id' => $shop->id,
            'actor_kind' => 'staff', 'actor_user_id' => $owner->id, 'event_kind' => 'created',
            'after_starts_at' => '2026-10-05 10:00:00', 'after_status' => Appointment::STATUS_SCHEDULED,
        ]);

        $appointment->update(['starts_at' => '2026-10-05 10:00:00', 'ends_at' => '2026-10-05 10:30:00']);
        $this->actingAs($owner)->put(route('tenant.appointments.reschedule', [$shop, $appointment], false), [
            'starts_at' => '2026-10-05T11:00',
        ])->assertRedirect();
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id, 'event_kind' => 'rescheduled',
            'before_starts_at' => '2026-10-05 10:00:00', 'after_starts_at' => '2026-10-05 11:00:00',
        ]);

        $this->travelTo('2026-10-07 12:00:00 America/Mexico_City');
        foreach ([['complete', Appointment::STATUS_COMPLETED], ['no-show', Appointment::STATUS_NO_SHOW], ['cancel', Appointment::STATUS_CANCELLED]] as [$action, $status]) {
            $record = Appointment::factory()->for($shop)->for($barber)->for($service)->create([
                'starts_at' => '2026-10-06 10:00:00', 'ends_at' => '2026-10-06 10:30:00',
            ]);
            $this->actingAs($owner)->post(route("tenant.appointments.{$action}", [$shop, $record], false))->assertRedirect();
            $this->assertDatabaseHas('appointment_events', [
                'appointment_id' => $record->id, 'actor_kind' => 'staff', 'actor_user_id' => $owner->id,
                'event_kind' => $status, 'before_starts_at' => '2026-10-06 10:00:00', 'after_starts_at' => '2026-10-06 10:00:00',
                'before_status' => Appointment::STATUS_SCHEDULED, 'after_status' => $status,
            ]);
        }
    }
}
