<?php

use App\Http\Controllers\CustomerProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('/barbershops/{barbershop:slug}/profile')->name('customer.profile.')->group(function (): void {
    Route::get('/', [CustomerProfileController::class, 'show'])->name('show');
    Route::put('/preferences', [CustomerProfileController::class, 'updatePreferences'])->name('preferences.update');
});
