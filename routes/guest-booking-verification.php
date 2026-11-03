<?php

use App\Http\Controllers\GuestBookingEmailCodeController;
use Illuminate\Support\Facades\Route;

Route::prefix('/barbershops/{barbershop:slug}/booking/email-code')->group(function (): void {
    Route::post('/', [GuestBookingEmailCodeController::class, 'store'])->name('public.booking.email-code.store');
    Route::post('/verify', [GuestBookingEmailCodeController::class, 'verify'])->name('public.booking.email-code.verify');
});
