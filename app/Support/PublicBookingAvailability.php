<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Service;
use Carbon\CarbonImmutable;

final class PublicBookingAvailability
{
    /** @return array<int, array{starts_at: string, barber_id: int, barber_name: string}> */
    public function slots(Barbershop $barbershop, Service $service, string $date, ?int $barberId = null): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $barbershop->timezone);
        $weekday = $day->dayOfWeek;
        $businessHours = $barbershop->businessHours()
            ->where('weekday', $weekday)
            ->where('closed', false)
            ->first();

        if ($businessHours === null || $businessHours->opens_at === null || $businessHours->closes_at === null) {
            return [];
        }

        $barbers = $barbershop->barbers()
            ->where('active', true)
            ->whereHas('services', fn ($query) => $query->whereKey($service->id)->where('active', true))
            ->when($barberId !== null, fn ($query) => $query->whereKey($barberId))
            ->orderBy('id')
            ->get();
        $slots = [];
        $now = CarbonImmutable::now($barbershop->timezone);
        $duration = (int) $service->duration_minutes;

        foreach ($barbers as $barber) {
            $availability = $barber->availabilities()
                ->where('weekday', $weekday)
                ->where('active', true)
                ->first();

            if ($availability === null) {
                continue;
            }

            $open = $day->setTimeFromTimeString((string) $businessHours->opens_at);
            $close = $day->setTimeFromTimeString((string) $businessHours->closes_at);
            $availableFrom = $day->setTimeFromTimeString((string) $availability->starts_at);
            $availableUntil = $day->setTimeFromTimeString((string) $availability->ends_at);
            $start = $open->greaterThan($availableFrom) ? $open : $availableFrom;
            $end = $close->lessThan($availableUntil) ? $close : $availableUntil;
            $minute = (int) $start->format('i');
            $start = $start->setMinute((int) (ceil($minute / 15) * 15))->setSecond(0);

            for ($candidate = $start; $candidate->copy()->addMinutes($duration)->lessThanOrEqualTo($end); $candidate = $candidate->addMinutes(15)) {
                if (! $candidate->greaterThan($now)) {
                    continue;
                }

                $candidateEnd = $candidate->addMinutes($duration);
                $overlaps = $barber->appointments()
                    ->where('status', Appointment::STATUS_SCHEDULED)
                    ->where('starts_at', '<', $candidateEnd)
                    ->where('ends_at', '>', $candidate)
                    ->exists();

                if (! $overlaps) {
                    $key = $candidate->format('Y-m-d\TH:i');
                    $slots[$key] ??= [
                        'starts_at' => $key,
                        'barber_id' => $barber->id,
                        'barber_name' => $barber->display_name,
                    ];
                }
            }
        }

        ksort($slots);

        return array_values($slots);
    }
}
