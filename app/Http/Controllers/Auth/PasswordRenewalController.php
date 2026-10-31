<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class PasswordRenewalController extends Controller
{
    public function create(): View
    {
        return view('auth.password-renewal');
    }
}
