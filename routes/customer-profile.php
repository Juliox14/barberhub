<?php

use App\Http\Controllers\CustomerProfileController;
use App\Http\Controllers\CustomerReminderController;
use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('/barbershops/{barbershop:slug}/profile')->name('customer.profile.')->group(function (): void {
    Route::get('/', [CustomerProfileController::class, 'show'])->name('show');
    Route::put('/preferences', [CustomerProfileController::class, 'updatePreferences'])->name('preferences.update');
    Route::put('/reminders', [CustomerReminderController::class, 'update'])->name('reminders.update');
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'storeForCustomer'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions/{subscription}', [PushSubscriptionController::class, 'destroyForCustomer'])->name('push-subscriptions.destroy');
});
