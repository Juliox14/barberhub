<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::parse('2026-10-05 10:00:00');
        $endsAt = $startsAt->copy()->addMinutes(30);

        return [
            'barbershop_id' => Barbershop::factory(),
            'barber_id' => Barber::factory(),
            'service_id' => Service::factory(),
            'customer_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => Appointment::STATUS_SCHEDULED,
        ];
    }

    public function forCustomer(): static
    {
        return $this->state(fn (): array => [
            'customer_id' => Customer::factory(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => Appointment::STATUS_COMPLETED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => Appointment::STATUS_CANCELLED,
        ]);
    }
}
