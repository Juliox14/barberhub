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
use App\Support\ModuleContainment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBookingAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_local_available_slots_without_exposing_appointments(): void
    {
        $this->travelTo('2026-10-05 08:00:00 UTC');
        [$shop, $service, $barber] = $this->availabilityContext('America/Mexico_City');
        Appointment::factory()->for($shop)->for($barber)->for($service)->create([
            'starts_at' => '2026-10-05 10:00:00',
            'ends_at' => '2026-10-05 10:30:00',
        ]);

        $this->getJson(route('public.availability', [
            'barbershop' => $shop->slug,
            'service_id' => $service->id,
            'date' => '2026-10-05',
        ]))->assertOk()->assertExactJson(['slots' => [
            ['starts_at' => '2026-10-05T10:30', 'barber_id' => $barber->id, 'barber_name' => $barber->display_name],
        ]]);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_excludes_a_local_time_appointment_created_through_the_existing_booking_endpoint(): void
    {
        $this->travelTo('2026-10-05 08:00:00 UTC');
        [$shop, $service, $barber] = $this->availabilityContext('America/Mexico_City');
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($shop)->create([
            'role' => Membership::ROLE_OWNER,
        ]);

        $this->actingAs($owner)->post(route('tenant.appointments.store', $shop), [
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'starts_at' => '2026-10-05T10:00',
        ])->assertRedirect();
        $this->assertDatabaseCount('appointments', 1);
        $this->app['auth']->forgetGuards();

        $this->getJson(route('public.availability', [
            'barbershop' => $shop->slug,
            'service_id' => $service->id,
            'date' => '2026-10-05',
        ]))->assertOk()->assertExactJson(['slots' => [
            ['starts_at' => '2026-10-05T10:30', 'barber_id' => $barber->id, 'barber_name' => $barber->display_name],
        ]]);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_uses_lowest_barber_id_for_unpreferred_slots_and_honors_selected_barber(): void
    {
        $this->travelTo('2026-10-05 08:00:00 UTC');
        [$shop, $service, $barber] = $this->availabilityContext();
        $otherBarber = Barber::factory()->for($shop)->create(['display_name' => 'Segundo']);
        BarberAvailability::factory()->for($otherBarber)->create(['weekday' => BusinessHour::WEEKDAY_MONDAY]);

        $this->getJson(route('public.availability', ['barbershop' => $shop->slug, 'service_id' => $service->id, 'date' => '2026-10-05']))
            ->assertOk()->assertJsonPath('slots.0.barber_id', min($barber->id, $otherBarber->id));
        $this->getJson(route('public.availability', ['barbershop' => $shop->slug, 'service_id' => $service->id, 'date' => '2026-10-05', 'barber_id' => $otherBarber->id]))
            ->assertOk()->assertJsonPath('slots.0.barber_id', $otherBarber->id);
    }

    public function test_hides_inactive_cross_tenant_and_suspended_shops(): void
    {
        $this->travelTo('2026-10-05 08:00:00 UTC');
        [$shop, $service] = $this->availabilityContext();
        $otherShop = Barbershop::factory()->create();
        $foreignService = Service::factory()->for($otherShop)->create();

        $this->getJson(route('public.availability', ['barbershop' => $shop->slug, 'service_id' => $foreignService->id, 'date' => '2026-10-05']))->assertNotFound();
        $service->update(['active' => false]);
        $this->getJson(route('public.availability', ['barbershop' => $shop->slug, 'service_id' => $service->id, 'date' => '2026-10-05']))->assertNotFound();
        $service->update(['active' => true]);
        $barber = $shop->barbers()->firstOrFail();
        $barber->update(['active' => false]);
        $this->getJson(route('public.availability', ['barbershop' => $shop->slug, 'service_id' => $service->id, 'date' => '2026-10-05', 'barber_id' => $barber->id]))->assertNotFound();
        $shop->update(['status' => Barbershop::STATUS_INACTIVE]);
        $this->getJson(route('public.availability', ['barbershop' => $shop->slug, 'service_id' => $service->id, 'date' => '2026-10-05']))->assertNotFound();
        $shop->update(['status' => Barbershop::STATUS_ACTIVE]);
        app(ModuleContainment::class)->suspend($shop, 'appointments', 'test');
        $this->getJson(route('public.availability', ['barbershop' => $shop->slug, 'service_id' => $service->id, 'date' => '2026-10-05']))->assertNotFound();
    }

    public function test_rejects_invalid_past_and_too_far_dates(): void
    {
        $this->travelTo('2026-10-05 12:00:00 UTC');
        [$shop, $service] = $this->availabilityContext();
        foreach (['2026-02-30', '2026-10-04', '2027-01-04'] as $date) {
            $this->getJson(route('public.availability', ['barbershop' => $shop->slug, 'service_id' => $service->id, 'date' => $date]))
                ->assertUnprocessable()->assertJsonValidationErrors('date');
        }
    }

    /** @return array{Barbershop, Service, Barber} */
    private function availabilityContext(string $timezone = 'UTC'): array
    {
        $shop = Barbershop::factory()->create(['timezone' => $timezone]);
        $service = Service::factory()->for($shop)->create(['duration_minutes' => 30]);
        $barber = Barber::factory()->for($shop)->create();
        BusinessHour::factory()->for($shop)->create(['weekday' => BusinessHour::WEEKDAY_MONDAY, 'opens_at' => '10:00', 'closes_at' => '11:00']);
        BarberAvailability::factory()->for($barber)->create(['weekday' => BusinessHour::WEEKDAY_MONDAY, 'starts_at' => '10:00', 'ends_at' => '11:00']);

        return [$shop, $service, $barber];
    }
}
