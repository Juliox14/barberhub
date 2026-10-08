<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\Service;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentScheduler
{
    public function schedule(
        Barbershop $barbershop,
        Barber $barber,
        Service $service,
        ?Customer $customer,
        CarbonInterface $startsAt,
    ): Appointment {
        $endsAt = $startsAt->copy()->addMinutes((int) $service->duration_minutes);

        return DB::transaction(function () use ($barbershop, $barber, $service, $customer, $startsAt, $endsAt): Appointment {
            $this->validate($barbershop, $barber, $service, $customer, $startsAt, $endsAt);

            return Appointment::create([
                'barbershop_id' => $barbershop->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'customer_id' => $customer?->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => Appointment::STATUS_SCHEDULED,
            ]);
        });
    }

    private function validate(
        Barbershop $barbershop,
        Barber $barber,
        Service $service,
        ?Customer $customer,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): void {
        $errors = [];

        if ($barber->barbershop_id !== $barbershop->id || ! $barber->active) {
            $errors['barber'] = ['Selecciona un barbero activo de esta barbería.'];
        }

        if ($service->barbershop_id !== $barbershop->id || ! $service->active) {
            $errors['service'] = ['Selecciona un servicio activo de esta barbería.'];
        }

        if ($customer !== null && $customer->barbershop_id !== $barbershop->id) {
            $errors['customer'] = ['Selecciona un cliente de esta barbería.'];
        }

        if ($startsAt->minute % 15 !== 0 || $startsAt->second !== 0) {
            $errors['starts_at'] = ['La cita debe comenzar en un bloque de 15 minutos.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->validateBusinessHourBounds($barbershop, $startsAt, $endsAt);
        $this->validateBarberAvailabilityBounds($barber, $startsAt, $endsAt);
        $this->validateNoActiveOverlap($barber, $startsAt, $endsAt);
    }

    private function validateBusinessHourBounds(Barbershop $barbershop, CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        $businessHour = $barbershop->businessHours()
            ->where('weekday', $startsAt->dayOfWeek)
            ->where('closed', false)
            ->first();

        if ($businessHour === null || $businessHour->opens_at === null || $businessHour->closes_at === null) {
            throw ValidationException::withMessages([
                'starts_at' => ['La cita debe estar dentro del horario de negocio del día seleccionado.'],
            ]);
        }

        if (! $this->timeWindowContains($businessHour->opens_at, $businessHour->closes_at, $startsAt, $endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => ['La cita debe estar dentro del horario de negocio del día seleccionado.'],
            ]);
        }
    }

    private function validateBarberAvailabilityBounds(Barber $barber, CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        $availability = $barber->availabilities()
            ->where('weekday', $startsAt->dayOfWeek)
            ->where('active', true)
            ->first();

        if ($availability === null || ! $this->timeWindowContains($availability->starts_at, $availability->ends_at, $startsAt, $endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => ['La cita debe estar dentro de la disponibilidad activa del barbero.'],
            ]);
        }
    }

    private function validateNoActiveOverlap(Barber $barber, CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        $overlaps = $barber->appointments()
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->lockForUpdate()
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'starts_at' => ['El barbero ya tiene una cita activa en ese horario.'],
            ]);
        }
    }

    private function timeWindowContains(string $windowStart, string $windowEnd, CarbonInterface $startsAt, CarbonInterface $endsAt): bool
    {
        $appointmentStart = $startsAt->format('H:i');
        $appointmentEnd = $endsAt->format('H:i');

        return $appointmentStart >= mb_substr($windowStart, 0, 5)
            && $appointmentEnd <= mb_substr($windowEnd, 0, 5);
    }
}
