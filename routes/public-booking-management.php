<?php

use App\Http\Controllers\PublicBookingManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/barbershops/{barbershop:slug}/appointments/{appointment}/manage', [PublicBookingManagementController::class, 'show'])
    ->middleware('signed')
    ->name('public.booking.management');
