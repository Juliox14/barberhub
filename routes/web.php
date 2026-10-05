<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlatformBarbershopController;
use App\Http\Controllers\PlatformDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\TenantSelectorController;
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
    Route::get('/barbershops/{barbershop:slug}/dashboard', TenantDashboardController::class)
        ->middleware('tenant.member')
        ->name('tenant.dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
