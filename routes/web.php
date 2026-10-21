<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlatformBarbershopController;
use App\Http\Controllers\PlatformDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantAppointmentController;
use App\Http\Controllers\TenantBarberAvailabilityController;
use App\Http\Controllers\TenantBarberController;
use App\Http\Controllers\TenantBusinessHourController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\TenantMembershipController;
use App\Http\Controllers\TenantSelectorController;
use App\Http\Controllers\TenantServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('platform.admin')->prefix('platform')->name('platform.')->group(function (): void {
        Route::get('/dashboard', PlatformDashboardController::class)->name('dashboard');
        Route::get('/barbershops', [PlatformBarbershopController::class, 'index'])->name('barbershops.index');
        Route::get('/barbershops/create', [PlatformBarbershopController::class, 'create'])->name('barbershops.create');
        Route::post('/barbershops', [PlatformBarbershopController::class, 'store'])->name('barbershops.store');
        Route::get('/barbershops/{barbershop}/edit', [PlatformBarbershopController::class, 'edit'])->name('barbershops.edit');
        Route::put('/barbershops/{barbershop}', [PlatformBarbershopController::class, 'update'])->name('barbershops.update');
        Route::delete('/barbershops/{barbershop}', [PlatformBarbershopController::class, 'destroy'])->name('barbershops.destroy');
    });

    Route::get('/barbershops/select', TenantSelectorController::class)->name('tenant.selector');
    Route::middleware('tenant.member')->prefix('/barbershops/{barbershop:slug}')->group(function (): void {
        Route::get('/dashboard', TenantDashboardController::class)->name('tenant.dashboard');
        Route::get('/members', [TenantMembershipController::class, 'index'])->name('tenant.members.index');
        Route::post('/members', [TenantMembershipController::class, 'store'])->name('tenant.members.store');
        Route::put('/members/{membership}', [TenantMembershipController::class, 'update'])->name('tenant.members.update');
        Route::delete('/members/{membership}', [TenantMembershipController::class, 'destroy'])->name('tenant.members.destroy');
        Route::get('/barbers', [TenantBarberController::class, 'index'])->name('tenant.barbers.index');
        Route::post('/barbers', [TenantBarberController::class, 'store'])->name('tenant.barbers.store');
        Route::post('/barbers/invitations', [TenantBarberController::class, 'invite'])->name('tenant.barbers.invitations.store');
        Route::put('/barbers/{barber}', [TenantBarberController::class, 'update'])->name('tenant.barbers.update');
        Route::get('/services', [TenantServiceController::class, 'index'])->name('tenant.services.index');
        Route::post('/services', [TenantServiceController::class, 'store'])->name('tenant.services.store');
        Route::put('/services/{service}', [TenantServiceController::class, 'update'])->name('tenant.services.update');
        Route::get('/appointments', [TenantAppointmentController::class, 'index'])->name('tenant.appointments.index');
        Route::post('/appointments', [TenantAppointmentController::class, 'store'])->name('tenant.appointments.store');
        Route::get('/business-hours', [TenantBusinessHourController::class, 'index'])->name('tenant.business-hours.index');
        Route::post('/business-hours', [TenantBusinessHourController::class, 'store'])->name('tenant.business-hours.store');
        Route::put('/business-hours/{businessHour}', [TenantBusinessHourController::class, 'update'])->name('tenant.business-hours.update');
        Route::get('/barber-availability', [TenantBarberAvailabilityController::class, 'index'])->name('tenant.barber-availability.index');
        Route::post('/barber-availability', [TenantBarberAvailabilityController::class, 'store'])->name('tenant.barber-availability.store');
        Route::put('/barber-availability/{barberAvailability}', [TenantBarberAvailabilityController::class, 'update'])->name('tenant.barber-availability.update');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
