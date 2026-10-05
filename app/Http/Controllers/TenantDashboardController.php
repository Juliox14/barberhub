<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Models\Membership;
use App\Support\TenantDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TenantDashboardController extends Controller
{
    public function __invoke(Request $request, Barbershop $barbershop): View
    {
        $membership = TenantDashboard::membershipFor($request->user(), $barbershop);
        $activeMembers = $barbershop->memberships()
            ->with('user')
            ->where('status', Membership::STATUS_ACTIVE)
            ->orderBy('role')
            ->get();

        $activeBarbers = $activeMembers
            ->where('role', Membership::ROLE_BARBER)
            ->take(4)
            ->values();

        return view('dashboards.tenant', [
            'barbershop' => $barbershop,
            'roleLabel' => TenantDashboard::roleLabel($membership),
            'roleCopy' => TenantDashboard::roleCopy($membership),
            'activeMembers' => $activeMembers,
            'activeBarbers' => $activeBarbers,
            'activeBarberCount' => $activeMembers
                ->where('role', Membership::ROLE_BARBER)
                ->count(),
            'inactiveMemberCount' => $barbershop->memberships()
                ->where('status', Membership::STATUS_INACTIVE)
                ->count(),
            'canManageMembers' => $request->user()?->is_platform_admin
                || in_array($membership?->role, [Membership::ROLE_OWNER, Membership::ROLE_ADMIN], true),
        ]);
    }
}
