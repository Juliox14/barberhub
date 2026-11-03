<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Service;
use App\Support\ModuleContainment;
use App\Support\PublicBookingAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PublicBookingAvailabilityController extends Controller
{
    public function __invoke(
        Request $request,
        Barbershop $barbershop,
        ModuleContainment $containment,
        PublicBookingAvailability $availability,
    ): JsonResponse {
        abort_unless($barbershop->status === Barbershop::STATUS_ACTIVE, 404);
        abort_if($containment->isSuspended($barbershop, 'appointments'), 404);

        $validated = $request->validate([
            'service_id' => ['required', 'integer'],
            'date' => ['required', 'string', 'date_format:Y-m-d'],
            'barber_id' => ['sometimes', 'nullable', 'integer'],
        ]);
        $today = CarbonImmutable::today($barbershop->timezone);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $validated['date'], $barbershop->timezone);

        if ($date === false || $date->format('Y-m-d') !== $validated['date'] || $date->lt($today) || $date->gt($today->addDays(90))) {
            throw ValidationException::withMessages(['date' => 'Selecciona una fecha entre hoy y los próximos 90 días.']);
        }

        $service = Service::query()
            ->whereKey($validated['service_id'])
            ->where('barbershop_id', $barbershop->id)
            ->where('active', true)
            ->first();
        abort_if($service === null, 404);

        $barberId = $validated['barber_id'] ?? null;
        if ($barberId !== null && ! Barber::query()
            ->whereKey($barberId)
            ->where('barbershop_id', $barbershop->id)
            ->where('active', true)
            ->exists()) {
            abort(404);
        }

        return response()->json(['slots' => $availability->slots($barbershop, $service, $date->format('Y-m-d'), $barberId)]);
    }
}
