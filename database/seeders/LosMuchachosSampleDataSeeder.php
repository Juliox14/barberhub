<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\BarberAvailability;
use App\Models\Barbershop;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\CustomerPreference;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LosMuchachosSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $barbershop = Barbershop::query()
            ->where('name', 'Los Muchachos')
            ->sole();

        $barber = $barbershop->barbers()
            ->where('active', true)
            ->sole();

        DB::transaction(function () use ($barbershop, $barber): void {
            $serviceDetails = [
                ['name' => 'Corte clásico', 'description' => 'Corte tradicional con acabado y peinado.', 'price' => 250, 'duration_minutes' => 45],
                ['name' => 'Perfilado de barba', 'description' => 'Perfilado con toalla caliente y acabado.', 'price' => 180, 'duration_minutes' => 30],
                ['name' => 'Corte y barba', 'description' => 'Corte clásico y perfilado de barba.', 'price' => 380, 'duration_minutes' => 75],
                ['name' => 'Corte infantil', 'description' => 'Corte para niñas y niños.', 'price' => 200, 'duration_minutes' => 30],
            ];

            $services = collect($serviceDetails)->mapWithKeys(function (array $details) use ($barbershop): array {
                $service = $barbershop->services()->firstOrCreate(
                    ['name' => $details['name']],
                    $details,
                );

                return [$details['name'] => $service];
            });

            $customerDetails = [
                ['name' => 'Mateo Ramírez', 'email' => 'mateo.ramirez@example.test', 'phone' => '+52 55 5555 0101'],
                ['name' => 'Diego Hernández', 'email' => 'diego.hernandez@example.test', 'phone' => '+52 55 5555 0102'],
                ['name' => 'Santiago Morales', 'email' => 'santiago.morales@example.test', 'phone' => '+52 55 5555 0103'],
                ['name' => 'Emiliano Torres', 'email' => 'emiliano.torres@example.test', 'phone' => '+52 55 5555 0104'],
                ['name' => 'Sebastián Cruz', 'email' => 'sebastian.cruz@example.test', 'phone' => '+52 55 5555 0105'],
                ['name' => 'Nicolás Flores', 'email' => 'nicolas.flores@example.test', 'phone' => '+52 55 5555 0106'],
            ];

            $customers = collect($customerDetails)->map(function (array $details) use ($barbershop): Customer {
                $customer = $barbershop->customers()->firstOrCreate(
                    ['email' => $details['email']],
                    $details,
                );

                CustomerPreference::query()->firstOrCreate(
                    ['customer_id' => $customer->id],
                    ['preferences' => ['notes' => 'Cliente de ejemplo.']],
                );

                return $customer;
            });

            foreach (range(0, 6) as $weekday) {
                $barbershop->businessHours()->firstOrCreate(
                    ['weekday' => $weekday],
                    $weekday === BusinessHour::WEEKDAY_SUNDAY
                        ? ['opens_at' => null, 'closes_at' => null, 'closed' => true]
                        : ['opens_at' => '09:00', 'closes_at' => '18:00', 'closed' => false],
                );
            }

            foreach (range(BusinessHour::WEEKDAY_MONDAY, BusinessHour::WEEKDAY_SATURDAY) as $weekday) {
                BarberAvailability::query()->firstOrCreate(
                    ['barber_id' => $barber->id, 'weekday' => $weekday],
                    ['starts_at' => '09:00', 'ends_at' => '18:00', 'active' => true],
                );
            }

            $timezone = $barbershop->timezone ?: config('app.timezone');
            $pastMonday = CarbonImmutable::now($timezone)->startOfWeek()->subWeek()->setTime(10, 0);
            $pastTuesday = $pastMonday->addDay()->setTime(11, 0);
            $futureDates = $this->upcomingBusinessDates($timezone, 4);
            $startsAt = [$pastMonday, $pastTuesday, ...$futureDates];
            $statuses = [
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_SCHEDULED,
            ];
            $serviceNames = ['Corte clásico', 'Corte y barba', 'Perfilado de barba', 'Corte infantil', 'Corte clásico', 'Corte y barba'];
            $times = ['10:00', '11:00', '09:00', '11:00', '13:00', '15:00'];

            foreach ($customers->values() as $index => $customer) {
                if ($customer->appointments()->exists()) {
                    continue;
                }

                $start = $startsAt[$index];
                if ($index >= 2) {
                    $start = $start->setTimeFromTimeString($times[$index]);
                }
                $service = $services->get($serviceNames[$index]);

                $barbershop->appointments()->create([
                    'barber_id' => $barber->id,
                    'service_id' => $service->id,
                    'customer_id' => $customer->id,
                    'starts_at' => $start,
                    'ends_at' => $start->addMinutes($service->duration_minutes),
                    'status' => $statuses[$index],
                ]);
            }
        });
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function upcomingBusinessDates(string $timezone, int $count): array
    {
        $dates = [];
        $date = CarbonImmutable::now($timezone)->startOfDay()->addDay();

        while (count($dates) < $count) {
            if ($date->dayOfWeek !== BusinessHour::WEEKDAY_SUNDAY) {
                $dates[] = $date;
            }

            $date = $date->addDay();
        }

        return $dates;
    }
}
