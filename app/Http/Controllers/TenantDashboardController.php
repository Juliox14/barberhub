<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Support\TenantDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TenantDashboardController extends Controller
{
    public function __invoke(Request $request, Barbershop $barbershop): View
    {
        $membership = TenantDashboard::membershipFor($request->user(), $barbershop);

        return view('dashboards.tenant', [
            'barbershop' => $barbershop,
            'roleLabel' => TenantDashboard::roleLabel($membership),
            'roleCopy' => TenantDashboard::roleCopy($membership),
        ]);
    }
}
