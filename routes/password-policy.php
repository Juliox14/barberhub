<?php

use App\Http\Controllers\Auth\PasswordRenewalController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->get('/password/renew', [PasswordRenewalController::class, 'create'])
    ->name('password.renew');
