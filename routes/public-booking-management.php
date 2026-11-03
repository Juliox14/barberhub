<?php

use App\Http\Controllers\PublicBookingManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/barbershops/{barbershop:slug}/appointments/{appointment}/manage', [PublicBookingManagementController::class, 'show'])
    ->middleware('signed')
    ->name('public.booking.management');

Route::post('/barbershops/{barbershop:slug}/appointments/{appointment}/manage/date', [PublicBookingManagementController::class, 'chooseDate'])
    ->middleware('signed')
    ->name('public.booking.management.date');

Route::post('/barbershops/{barbershop:slug}/appointments/{appointment}/manage/cancel', [PublicBookingManagementController::class, 'cancel'])
    ->middleware('signed')
    ->name('public.booking.management.cancel');

Route::post('/barbershops/{barbershop:slug}/appointments/{appointment}/manage/reschedule', [PublicBookingManagementController::class, 'reschedule'])
    ->middleware('signed')
    ->name('public.booking.management.reschedule');
