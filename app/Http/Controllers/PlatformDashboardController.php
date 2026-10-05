<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->is_platform_admin, 403);

        return view('dashboards.platform', [
            'activeBarbershops' => Barbershop::query()
                ->where('status', Barbershop::STATUS_ACTIVE)
                ->count(),
            'inactiveBarbershops' => Barbershop::query()
                ->where('status', Barbershop::STATUS_INACTIVE)
                ->count(),
            'registeredUsers' => User::query()->count(),
            'activeMemberships' => Membership::query()
                ->where('status', Membership::STATUS_ACTIVE)
                ->count(),
            'barbershops' => Barbershop::query()
                ->withCount('memberships')
                ->latest()
                ->take(6)
                ->get(),
        ]);
    }
}
