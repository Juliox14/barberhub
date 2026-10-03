<?php

use App\Http\Controllers\DashboardController;
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
    Route::get('/platform/dashboard', PlatformDashboardController::class)->name('platform.dashboard');
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
