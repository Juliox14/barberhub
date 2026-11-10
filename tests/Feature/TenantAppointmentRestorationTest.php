<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Membership;
use App\Models\Service;
use App\Models\User;
use App\Support\AppointmentHistory;
use App\Support\AppointmentRestoration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantAppointmentRestorationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_owner_can_preview_and_restore_a_selected_historical_change(): void
    {
        [$shop, $owner, $appointment, $event] = $this->restorationContext();

        $this->actingAs($owner)
            ->get(route('tenant.appointments.restore.show', [$shop, $appointment, 'event' => $event->id], false))
            ->assertOk()
            ->assertSee('05/10/2026')
            ->assertSee('10:00')
            ->assertSee('11:00')
            ->assertSee('date=2026-10-05', false)
            ->assertSee('debe coincidir al confirmar')
            ->assertSee('name="_token"', false);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'starts_at' => '2026-10-05 11:00:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        $this->assertDatabaseCount('appointment_events', 1);

        $this->actingAs($owner)
            ->from(route('tenant.appointments.restore.show', [$shop, $appointment, 'event' => $event->id], false))
            ->post(route('tenant.appointments.restore', [$shop, $appointment], false), [
                'event_id' => $event->id,
                'expected_starts_at' => '2026-10-05 11:00:00',
                'expected_status' => Appointment::STATUS_SCHEDULED,
                'reason' => 'Corrección de una modificación no autorizada',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $shop, 'date' => '2026-10-05'], false));

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'starts_at' => '2026-10-05 10:00:00',
            'ends_at' => '2026-10-05 10:30:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id,
            'event_kind' => 'restored',
            'before_starts_at' => '2026-10-05 11:00:00',
            'after_starts_at' => '2026-10-05 10:00:00',
            'before_status' => Appointment::STATUS_SCHEDULED,
            'after_status' => Appointment::STATUS_SCHEDULED,
        ]);
        $this->assertDatabaseCount('appointment_events', 2);
        $audit = DB::table('security_audit_logs')->where('event', 'appointment.restored')->sole();
        $this->assertSame($owner->id, $audit->actor_id);
        $this->assertSame($shop->id, $audit->barbershop_id);
        $this->assertSame([
            'appointment_id' => (string) $appointment->id,
            'source_event_id' => (string) $event->id,
            'reason' => 'Corrección de una modificación no autorizada',
        ], json_decode($audit->metadata, true));
    }

    public function test_recovery_preview_and_redirect_keep_the_stored_local_date_near_midnight(): void
    {
        [$shop, $owner, $appointment, $event] = $this->restorationContext();
        $appointment->update(['starts_at' => '2026-10-05 02:00:00', 'ends_at' => '2026-10-05 02:30:00']);
        $event->update([
            'before_starts_at' => '2026-10-05 01:00:00',
            'after_starts_at' => '2026-10-05 02:00:00',
        ]);
        $shop->businessHours()->update(['opens_at' => '00:00', 'closes_at' => '06:00']);
        $appointment->barber->availabilities()->update(['starts_at' => '00:00', 'ends_at' => '06:00']);

        $this->actingAs($owner)
            ->get(route('tenant.appointments.index', ['barbershop' => $shop, 'date' => '2026-10-05'], false))
            ->assertOk()
            ->assertSee('05/10/2026 01:00')
            ->assertSee('05/10/2026 02:00')
            ->assertSee('event='.$event->id, false);

        $this->actingAs($owner)
            ->get(route('tenant.appointments.restore.show', [$shop, $appointment, 'event' => $event->id], false))
            ->assertOk()
            ->assertSee('05/10/2026')
            ->assertSee('01:00')
            ->assertSee('02:00')
            ->assertSee('date=2026-10-05', false);

        $this->actingAs($owner)
            ->post(route('tenant.appointments.restore', [$shop, $appointment], false), [
                'event_id' => $event->id,
                'expected_starts_at' => '2026-10-05 02:00:00',
                'expected_status' => Appointment::STATUS_SCHEDULED,
                'reason' => 'Recuperación cercana a medianoche',
            ])
            ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $shop, 'date' => '2026-10-05'], false));

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'starts_at' => '2026-10-05 01:00:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
    }

    public function test_owner_can_restore_a_cancelled_appointment_to_scheduled_with_history(): void
    {
        [$shop, $owner, $appointment, $event] = $this->restorationContext();
        $appointment->update(['status' => Appointment::STATUS_CANCELLED]);
        $event->update(['after_status' => Appointment::STATUS_CANCELLED]);

        $this->actingAs($owner)
            ->post(route('tenant.appointments.restore', [$shop, $appointment], false), [
                'event_id' => $event->id,
                'expected_starts_at' => '2026-10-05 11:00:00',
                'expected_status' => Appointment::STATUS_CANCELLED,
                'reason' => 'Restablecer cita cancelada por error',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'starts_at' => '2026-10-05 10:00:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id,
            'event_kind' => 'restored',
            'before_status' => Appointment::STATUS_CANCELLED,
            'after_status' => Appointment::STATUS_SCHEDULED,
        ]);
        $this->assertDatabaseCount('appointment_events', 2);

        $recoveryEvent = AppointmentEvent::query()->where('event_kind', 'restored')->sole();
        $this->assertTrue(app(AppointmentRestoration::class)->isRecoverable($recoveryEvent));
        $this->actingAs($owner)
            ->post(route('tenant.appointments.restore', [$shop, $appointment], false), [
                'event_id' => $recoveryEvent->id,
                'expected_starts_at' => '2026-10-05 10:00:00',
                'expected_status' => Appointment::STATUS_SCHEDULED,
                'reason' => 'Revertir una recuperación anterior',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'starts_at' => '2026-10-05 11:00:00',
            'status' => Appointment::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseCount('appointment_events', 3);
    }

    public function test_restoration_rejects_empty_or_overlong_reasons_without_changing_the_appointment(): void
    {
        [$shop, $owner, $appointment, $event] = $this->restorationContext();

        foreach (['', str_repeat('x', 501)] as $reason) {
            $this->actingAs($owner)
                ->from(route('tenant.appointments.restore.show', [$shop, $appointment, 'event' => $event->id], false))
                ->post(route('tenant.appointments.restore', [$shop, $appointment], false), [
                    'event_id' => $event->id,
                    'expected_starts_at' => '2026-10-05 11:00:00',
                    'expected_status' => Appointment::STATUS_SCHEDULED,
                    'reason' => $reason,
                ])
                ->assertRedirect(route('tenant.appointments.restore.show', [$shop, $appointment, 'event' => $event->id], false))
                ->assertSessionHasErrors('reason');
        }

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'starts_at' => '2026-10-05 11:00:00',
            'status' => Appointment::STATUS_SCHEDULED,
        ]);
        $this->assertDatabaseCount('appointment_events', 1);
    }

    public function test_restore_requires_csrf_token_and_rejects_missing_or_invalid_tokens_without_mutation(): void
    {
        [$shop, $owner, $appointment, $event] = $this->restorationContext();
        $this->withMiddleware();
        $this->actingAs($owner);
        $url = route('tenant.appointments.restore', [$shop, $appointment], false);
        $payload = [
            'event_id' => $event->id,
            'expected_starts_at' => '2026-10-05 11:00:00',
            'expected_status' => Appointment::STATUS_SCHEDULED,
            'reason' => 'Restaurar tras validar CSRF',
        ];
        $before = $appointment->fresh()->getAttributes();
        $environment = app()->environment();
        app()->instance('env', 'production');

        try {
            $this->post($url, $payload)->assertStatus(419);
            $this->assertSame($before, $appointment->fresh()->getAttributes());
            $this->assertDatabaseCount('appointment_events', 1);
            $this->assertDatabaseCount('security_audit_logs', 0);

            $token = str_repeat('c', 40);
            $this->withSession(['_token' => $token])
                ->post($url, [...$payload, '_token' => 'invalid-token'])
                ->assertStatus(419);
            $this->assertSame($before, $appointment->fresh()->getAttributes());
            $this->assertDatabaseCount('appointment_events', 1);
            $this->assertDatabaseCount('security_audit_logs', 0);

            $this->withSession(['_token' => $token])
                ->post($url, [...$payload, '_token' => $token])
                ->assertRedirect(route('tenant.appointments.index', ['barbershop' => $shop, 'date' => '2026-10-05'], false));
            $this->assertDatabaseHas('appointments', [
                'id' => $appointment->id,
                'starts_at' => '2026-10-05 10:00:00',
                'status' => Appointment::STATUS_SCHEDULED,
            ]);
            $this->assertDatabaseCount('appointment_events', 2);
            $this->assertDatabaseCount('security_audit_logs', 1);
        } finally {
            app()->instance('env', $environment);
        }
    }

    public function test_restoration_rejects_stale_current_state_and_cross_tenant_events(): void
    {
        [$shop, $owner, $appointment, $event] = $this->restorationContext();
        $this->actingAs($owner)
            ->get(route('tenant.appointments.restore.show', [$shop, $appointment, 'event' => $event->id], false))
            ->assertOk();
        $appointment->update(['starts_at' => '2026-10-05 11:15:00', 'ends_at' => '2026-10-05 11:45:00']);

        $otherShop = Barbershop::factory()->create();
        $otherAppointment = Appointment::factory()->for($otherShop)->create();
        $otherEvent = app(AppointmentHistory::class)->record(
            $otherAppointment,
            'rescheduled',
            'staff',
            $owner,
            '2026-10-05 10:00:00',
            '2026-10-05 11:00:00',
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_SCHEDULED,
        );

        $this->actingAs($owner)
            ->get(route('tenant.appointments.restore.show', [$shop, $appointment, 'event' => $otherEvent->id], false))
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('tenant.appointments.restore', [$shop, $appointment], false), [
                'event_id' => $event->id,
                'expected_starts_at' => '2026-10-05 11:00:00',
                'expected_status' => Appointment::STATUS_SCHEDULED,
                'reason' => 'Estado inesperado',
            ])
            ->assertSessionHasErrors('event_id');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'starts_at' => '2026-10-05 11:15:00',
        ]);
        $this->assertDatabaseCount('appointment_events', 2);
    }

    public function test_unprivileged_tenant_member_cannot_preview_or_restore(): void
    {
        [$shop, , $appointment, $event] = $this->restorationContext();
        $barberUser = User::factory()->create();
        Membership::factory()->for($barberUser)->for($shop)->create(['role' => Membership::ROLE_BARBER]);

        $this->actingAs($barberUser)
            ->get(route('tenant.appointments.restore.show', [$shop, $appointment, 'event' => $event->id], false))
            ->assertForbidden();

        $this->actingAs($barberUser)
            ->post(route('tenant.appointments.restore', [$shop, $appointment], false), [
                'event_id' => $event->id,
                'expected_starts_at' => '2026-10-05 11:00:00',
                'expected_status' => Appointment::STATUS_SCHEDULED,
                'reason' => 'Sin autorización',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'starts_at' => '2026-10-05 11:00:00']);
        $this->assertDatabaseCount('appointment_events', 1);
    }

    public function test_restoration_rejects_conflicting_schedule_without_mutation(): void
    {
        [$shop, $owner, $appointment, $event, $barber, $service] = $this->restorationContext();
        $appointment->update(['status' => Appointment::STATUS_CANCELLED]);
        $event->update(['after_status' => Appointment::STATUS_CANCELLED]);
        Appointment::factory()->for($shop)->for($barber)->for($service)->create([
            'starts_at' => '2026-10-05 10:00:00',
            'ends_at' => '2026-10-05 10:30:00',
        ]);

        $this->actingAs($owner)
            ->from(route('tenant.appointments.restore.show', [$shop, $appointment, 'event' => $event->id], false))
            ->post(route('tenant.appointments.restore', [$shop, $appointment], false), [
                'event_id' => $event->id,
                'expected_starts_at' => '2026-10-05 11:00:00',
                'expected_status' => Appointment::STATUS_CANCELLED,
                'reason' => 'Recuperación con conflicto',
            ])
            ->assertSessionHasErrors('starts_at');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'starts_at' => '2026-10-05 11:00:00',
            'ends_at' => '2026-10-05 11:30:00',
            'status' => Appointment::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseCount('appointment_events', 1);
    }

    /** @return array{0: Barbershop, 1: User, 2: Appointment, 3: AppointmentEvent, 4: Barber, 5: Service} */
    private function restorationContext(): array
    {
        $shop = Barbershop::factory()->create(['timezone' => 'America/Mexico_City']);
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($shop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($shop)->create(['active' => true]);
        $service = Service::factory()->for($shop)->create(['active' => true, 'duration_minutes' => 30]);
        BusinessHour::factory()->for($shop)->create([
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
        $appointment = Appointment::factory()->for($shop)->for($barber)->for($service)->create([
            'starts_at' => '2026-10-05 11:00:00',
            'ends_at' => '2026-10-05 11:30:00',
        ]);
        $event = app(AppointmentHistory::class)->record(
            $appointment,
            'rescheduled',
            'staff',
            $owner,
            '2026-10-05 10:00:00',
            '2026-10-05 11:00:00',
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_SCHEDULED,
        );

        return [$shop, $owner, $appointment, $event, $barber, $service];
    }
}
