<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        return view('dashboards.platform');
    }
}
