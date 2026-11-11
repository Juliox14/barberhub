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

    public function test_availability_only_returns_slots_for_the_selected_barbers_offered_services(): void
    {
        $this->travelTo('2026-10-05 08:00:00 UTC');
        [$shop, $service, $barber] = $this->availabilityContext();
        $unassigned = Service::factory()->for($shop)->create();

        $this->getJson(route('public.availability', ['barbershop' => $shop->slug, 'service_id' => $unassigned->id, 'date' => '2026-10-05']))
            ->assertOk()->assertExactJson(['slots' => []]);
        $this->getJson(route('public.availability', ['barbershop' => $shop->slug, 'service_id' => $service->id, 'date' => '2026-10-05', 'barber_id' => $barber->id]))
            ->assertOk()->assertJsonCount(3, 'slots');
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

    public function test_returns_bounded_calendar_days_using_selected_barber_and_service_duration(): void
    {
        $this->travelTo('2026-10-05 08:00:00 UTC');
        [$shop, $service, $barber] = $this->availabilityContext();
        $otherBarber = Barber::factory()->for($shop)->create();
        BarberAvailability::factory()->for($otherBarber)->create(['weekday' => BusinessHour::WEEKDAY_MONDAY]);
        Appointment::factory()->for($shop)->for($barber)->for($service)->create([
            'starts_at' => '2026-10-05 10:00:00',
            'ends_at' => '2026-10-05 10:30:00',
        ]);
        $service->update(['duration_minutes' => 45]);

        $this->getJson(route('public.availability', [
            'barbershop' => $shop->slug,
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'date' => '2026-10-05',
            'end_date' => '2026-10-07',
        ]))->assertOk()->assertExactJson(['days' => [
            ['date' => '2026-10-05', 'available' => false],
            ['date' => '2026-10-06', 'available' => false],
            ['date' => '2026-10-07', 'available' => false],
        ]]);
    }

    public function test_calendar_marks_an_open_free_day_available(): void
    {
        $this->travelTo('2026-10-05 08:00:00 UTC');
        [$shop, $service, $barber] = $this->availabilityContext();

        $this->getJson(route('public.availability', [
            'barbershop' => $shop->slug,
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'date' => '2026-10-05',
            'end_date' => '2026-10-05',
        ]))->assertOk()->assertExactJson(['days' => [['date' => '2026-10-05', 'available' => true]]]);
    }

    public function test_calendar_marks_a_shop_closed_day_unavailable(): void
    {
        $this->travelTo('2026-10-05 08:00:00 UTC');
        [$shop, $service, $barber] = $this->availabilityContext();

        $this->getJson(route('public.availability', [
            'barbershop' => $shop->slug,
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'date' => '2026-10-06',
            'end_date' => '2026-10-06',
        ]))->assertOk()->assertExactJson(['days' => [['date' => '2026-10-06', 'available' => false]]]);
    }

    public function test_calendar_marks_day_unavailable_when_service_duration_exceeds_opening(): void
    {
        $this->travelTo('2026-10-05 08:00:00 UTC');
        [$shop, $service, $barber] = $this->availabilityContext();
        $service->update(['duration_minutes' => 61]);

        $this->getJson(route('public.availability', [
            'barbershop' => $shop->slug,
            'service_id' => $service->id,
            'barber_id' => $barber->id,
            'date' => '2026-10-05',
            'end_date' => '2026-10-05',
        ]))->assertOk()->assertExactJson(['days' => [['date' => '2026-10-05', 'available' => false]]]);
    }

    public function test_full_selected_professional_is_unavailable_even_when_another_professional_is_free(): void
    {
        $this->travelTo('2026-10-05 08:00:00 UTC');
        [$shop, $service, $selectedBarber] = $this->availabilityContext();
        $otherBarber = Barber::factory()->for($shop)->create();
        BarberAvailability::factory()->for($otherBarber)->create(['weekday' => BusinessHour::WEEKDAY_MONDAY]);
        $otherBarber->services()->syncWithoutDetaching([$service->id]);
        foreach ([['10:00', '10:30'], ['10:15', '10:45'], ['10:30', '11:00']] as [$start, $end]) {
            Appointment::factory()->for($shop)->for($selectedBarber)->for($service)->create([
                'starts_at' => '2026-10-05 '.$start.':00',
                'ends_at' => '2026-10-05 '.$end.':00',
            ]);
        }

        $this->getJson(route('public.availability', [
            'barbershop' => $shop->slug,
            'service_id' => $service->id,
            'barber_id' => $selectedBarber->id,
            'date' => '2026-10-05',
            'end_date' => '2026-10-05',
        ]))->assertOk()->assertExactJson(['days' => [['date' => '2026-10-05', 'available' => false]]]);
        $this->getJson(route('public.availability', [
            'barbershop' => $shop->slug,
            'service_id' => $service->id,
            'date' => '2026-10-05',
            'barber_id' => $otherBarber->id,
        ]))->assertOk()->assertJsonCount(3, 'slots');
    }

    public function test_rejects_calendar_ranges_longer_than_31_days(): void
    {
        $this->travelTo('2026-10-05 12:00:00 UTC');
        [$shop, $service] = $this->availabilityContext();

        $this->getJson(route('public.availability', [
            'barbershop' => $shop->slug,
            'service_id' => $service->id,
            'date' => '2026-10-05',
            'end_date' => '2026-11-05',
        ]))->assertUnprocessable()->assertJsonValidationErrors('end_date');
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
        $barber->services()->syncWithoutDetaching([$service->id]);

        return [$shop, $service, $barber];
    }
}
