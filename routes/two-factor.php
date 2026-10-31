<?php

use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorEnrollmentController;
use Illuminate\Support\Facades\Route;

Route::get('/two-factor/challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
Route::post('/two-factor/challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:20,1')->name('two-factor.verify');
Route::post('/two-factor/cancel', [TwoFactorChallengeController::class, 'cancel'])->name('two-factor.cancel');

Route::middleware(['auth', 'throttle:10,1'])->prefix('two-factor')->name('two-factor.')->group(function (): void {
    Route::get('/settings', [TwoFactorEnrollmentController::class, 'show'])->name('settings');
    Route::post('/begin', [TwoFactorEnrollmentController::class, 'begin'])->name('begin');
    Route::post('/confirm', [TwoFactorEnrollmentController::class, 'confirm'])->name('confirm');
    Route::post('/recovery', [TwoFactorEnrollmentController::class, 'regenerateRecoveryCodes'])->name('recovery');
    Route::delete('/disable', [TwoFactorEnrollmentController::class, 'disable'])->name('disable');
});
