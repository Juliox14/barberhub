<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Barbershop;
use App\Support\ModuleContainment;
use App\Support\TenantBranding;
use Illuminate\Http\Response;

final class PublicBookingManagementController extends Controller
{
    public function show(Barbershop $barbershop, Appointment $appointment, ModuleContainment $containment): Response
    {
        abort_unless($barbershop->status === Barbershop::STATUS_ACTIVE, 404);
        abort_if($containment->isSuspended($barbershop, 'appointments'), 404);

        $appointment = $barbershop->appointments()
            ->with(['service', 'barber', 'customer'])
            ->whereKey($appointment->getKey())
            ->firstOrFail();

        return response()->view('public.booking-management', [
            'barbershop' => $barbershop,
            'branding' => TenantBranding::resolve($barbershop),
            'appointment' => $appointment,
        ])->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
