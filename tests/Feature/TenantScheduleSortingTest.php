<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScheduleSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_hours_sort_weekdays_numerically(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        BusinessHour::factory()->for($barbershop)->create(['weekday' => BusinessHour::WEEKDAY_SUNDAY]);
        BusinessHour::factory()->for($barbershop)->create(['weekday' => BusinessHour::WEEKDAY_MONDAY]);

        $this->actingAs($owner)
            ->get(route('tenant.business-hours.index', ['barbershop' => $barbershop, 'sort' => 'weekday', 'direction' => 'desc'], false))
            ->assertOk()
            ->assertViewHas('businessHours', fn ($hours): bool => $hours->pluck('weekday')->all() === [BusinessHour::WEEKDAY_MONDAY, BusinessHour::WEEKDAY_SUNDAY]);
        $this->get(route('tenant.business-hours.index', ['barbershop' => $barbershop, 'sort' => 'weekday', 'direction' => 'asc'], false))
            ->assertViewHas('businessHours', fn ($hours): bool => $hours->pluck('weekday')->all() === [BusinessHour::WEEKDAY_SUNDAY, BusinessHour::WEEKDAY_MONDAY]);
        $this->get(route('tenant.business-hours.index', ['barbershop' => $barbershop, 'sort' => 'unknown', 'direction' => 'desc'], false))
            ->assertViewHas('businessHours', fn ($hours): bool => $hours->pluck('weekday')->all() === [BusinessHour::WEEKDAY_SUNDAY, BusinessHour::WEEKDAY_MONDAY]);
        $this->get(route('tenant.business-hours.index', ['barbershop' => $barbershop, 'sort' => 'weekday', 'direction' => 'sideways'], false))
            ->assertViewHas('businessHours', fn ($hours): bool => $hours->pluck('weekday')->all() === [BusinessHour::WEEKDAY_SUNDAY, BusinessHour::WEEKDAY_MONDAY]);
    }

    public function test_barber_availability_sorts_weekdays_numerically_within_each_barber(): void
    {
        $barbershop = Barbershop::factory()->create();
        $owner = User::factory()->create();
        Membership::factory()->for($owner)->for($barbershop)->create(['role' => Membership::ROLE_OWNER]);
        $barber = Barber::factory()->for($barbershop)->create();
        $sunday = BarberAvailability::factory()->for($barber)->create(['weekday' => BusinessHour::WEEKDAY_SUNDAY, 'starts_at' => '15:00', 'ends_at' => '18:00']);
        $monday = BarberAvailability::factory()->for($barber)->create(['weekday' => BusinessHour::WEEKDAY_MONDAY, 'starts_at' => '09:00', 'ends_at' => '12:00']);

        $this->actingAs($owner)
            ->get(route('tenant.barber-availability.index', ['barbershop' => $barbershop, 'sort' => 'weekday', 'direction' => 'desc'], false))
            ->assertOk()
            ->assertViewHas('barbers', fn ($barbers): bool => $barbers->first()->availabilities->modelKeys() === [$monday->id, $sunday->id]);
        $this->get(route('tenant.barber-availability.index', ['barbershop' => $barbershop, 'sort' => 'weekday', 'direction' => 'asc'], false))
            ->assertViewHas('barbers', fn ($barbers): bool => $barbers->first()->availabilities->modelKeys() === [$sunday->id, $monday->id]);
        $this->get(route('tenant.barber-availability.index', ['barbershop' => $barbershop, 'sort' => 'starts_at', 'direction' => 'asc'], false))
            ->assertViewHas('barbers', fn ($barbers): bool => $barbers->first()->availabilities->modelKeys() === [$monday->id, $sunday->id]);
        $this->get(route('tenant.barber-availability.index', ['barbershop' => $barbershop, 'sort' => 'starts_at', 'direction' => 'desc'], false))
            ->assertViewHas('barbers', fn ($barbers): bool => $barbers->first()->availabilities->modelKeys() === [$sunday->id, $monday->id]);
        foreach ([
            ['sort' => 'unknown', 'direction' => 'desc'],
            ['sort' => 'weekday', 'direction' => 'sideways'],
        ] as $invalidSort) {
            $this->get(route('tenant.barber-availability.index', ['barbershop' => $barbershop, ...$invalidSort], false))
                ->assertViewHas('barbers', fn ($barbers): bool => $barbers->first()->availabilities->modelKeys() === [$sunday->id, $monday->id]);
        }
    }
}
