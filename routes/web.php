<?php

use App\Http\Controllers\ProfileController;
use App\Support\RoleDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function (): RedirectResponse {
    return redirect()->route(RoleDashboard::routeNameFor(request()->user()));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('/admin/dashboard', 'dashboards.admin')
        ->middleware('role:admin')
        ->name('admin.dashboard');

    Route::view('/barber/dashboard', 'dashboards.barber')
        ->middleware('role:barber')
        ->name('barber.dashboard');

    Route::view('/client/dashboard', 'dashboards.client')
        ->middleware('role:client')
        ->name('client.dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
