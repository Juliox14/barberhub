<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\User;
use Database\Seeders\LosMuchachosSampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LosMuchachosSampleDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_sample_data_without_creating_or_changing_barbers_and_is_repeatable(): void
    {
        $barbershop = Barbershop::factory()->create([
            'name' => 'Los Muchachos',
            'timezone' => 'America/Mexico_City',
        ]);
        $barber = Barber::factory()->for($barbershop)->create([
            'display_name' => 'Sheco',
            'active' => true,
        ]);
        $userCount = User::query()->count();
        $barberAttributes = $barber->refresh()->getAttributes();

        $seeder = new LosMuchachosSampleDataSeeder;
        $seeder->run();

        $this->assertSame($userCount, User::query()->count());
        $this->assertSame(1, Barber::query()->where('barbershop_id', $barbershop->id)->count());
        $this->assertSame($barberAttributes, $barber->fresh()->getAttributes());
        $this->assertDatabaseCount('services', 4);
        $this->assertDatabaseCount('customers', 6);
        $this->assertDatabaseCount('customer_preferences', 6);
        $this->assertDatabaseCount('business_hours', 7);
        $this->assertDatabaseCount('barber_availabilities', 6);
        $this->assertDatabaseCount('appointments', 6);
        $this->assertSame([$barber->id], Appointment::query()->distinct()->pluck('barber_id')->all());

        $seeder->run();

        $this->assertSame(1, Barber::query()->where('barbershop_id', $barbershop->id)->count());
        $this->assertDatabaseCount('services', 4);
        $this->assertDatabaseCount('customers', 6);
        $this->assertDatabaseCount('customer_preferences', 6);
        $this->assertDatabaseCount('business_hours', 7);
        $this->assertDatabaseCount('barber_availabilities', 6);
        $this->assertDatabaseCount('appointments', 6);
    }
}
