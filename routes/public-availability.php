<?php

use App\Http\Controllers\PublicBookingAvailabilityController;
use Illuminate\Support\Facades\Route;

Route::get('/barbershops/{barbershop:slug}/availability', PublicBookingAvailabilityController::class)
    ->name('public.availability');
