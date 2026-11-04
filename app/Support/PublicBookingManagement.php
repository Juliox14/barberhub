<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Barbershop;
use Illuminate\Support\Facades\URL;

final class PublicBookingManagement
{
    public static function url(Barbershop $barbershop, Appointment $appointment): string
    {
        return URL::signedRoute('public.booking.management', [
            'barbershop' => $barbershop,
            'appointment' => $appointment,
        ]);
    }
}
