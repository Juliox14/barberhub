<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SessionStatusController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CompleteBarberInvitationController;
use App\Http\Controllers\CustomerInvitationController;
use Illuminate\Support\Facades\Route;

Route::get('customer-invitations/{token}', [CustomerInvitationController::class, 'show'])
    ->name('customer-invitations.show');
Route::post('customer-invitations/{token}/register', [CustomerInvitationController::class, 'register'])
    ->name('customer-invitations.register');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::post('login/session-conflict/confirm', [AuthenticatedSessionController::class, 'confirmSessionTransfer'])
        ->name('login.session-conflict.confirm');
    Route::post('login/session-conflict/cancel', [AuthenticatedSessionController::class, 'cancelSessionTransfer'])
        ->name('login.session-conflict.cancel');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::get('barber-invitations/{token}', [CompleteBarberInvitationController::class, 'create'])
        ->name('barber-invitations.accept');

    Route::post('barber-invitations', [CompleteBarberInvitationController::class, 'store'])
        ->name('barber-invitations.complete');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::get('session/status', [SessionStatusController::class, 'show'])
    ->name('session.status');

Route::middleware('auth')->group(function () {
    Route::post('customer-invitations/{token}/accept', [CustomerInvitationController::class, 'accept'])
        ->name('customer-invitations.accept');

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
