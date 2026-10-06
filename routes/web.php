<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlatformBarbershopController;
use App\Http\Controllers\PlatformDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantBarberController;
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
        Route::put('/barbers/{barber}', [TenantBarberController::class, 'update'])->name('tenant.barbers.update');
        Route::get('/services', [TenantServiceController::class, 'index'])->name('tenant.services.index');
        Route::post('/services', [TenantServiceController::class, 'store'])->name('tenant.services.store');
        Route::put('/services/{service}', [TenantServiceController::class, 'update'])->name('tenant.services.update');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
